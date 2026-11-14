<?php

use App\Actions\Crm\RegisterContract;
use App\Actions\Referrals\Commissions;
use App\Enums\CommissionStatus;
use App\Enums\ContractStatus;
use App\Enums\Role;
use App\Livewire\Referrals\CommissionRules;
use App\Livewire\Referrals\CommissionsIndex;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\RegionalIndicator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->manager = userWithRole(Role::Manager);
    $this->exec = Executive::factory()->create();
});

function rule(array $attrs): CommissionRule
{
    return CommissionRule::create(array_merge(['name' => 'Regra '.uniqid(), 'active' => true], $attrs));
}

function opportunityFor(?RegionalIndicator $indicator): Opportunity
{
    $lead = Lead::factory()->create(['executive_id' => test()->exec->id, 'regional_indicator_id' => $indicator?->id]);

    return Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => test()->exec->id, 'regional_indicator_id' => $indicator?->id]);
}

function sign(Opportunity $o, string $value = '3500.00', ?string $expected = '3000.00')
{
    return app(RegisterContract::class)->handle($o, [
        'status' => ContractStatus::Signed, 'signed_at' => now()->toDateString(), 'contract_value' => $value, 'expected_revenue' => $expected,
    ], test()->manager);
}

it('gera comissão de valor fixo na assinatura', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'fixed', 'value' => '150.00', 'trigger_event' => 'contract_signed'])->id]);

    sign(opportunityFor($indicator));

    $c = Commission::firstOrFail();
    expect($c->expected_amount)->toBe('150.00')
        ->and($c->status)->toBe(CommissionStatus::Expected)
        ->and($c->base_amount)->toBeNull();
});

it('gera percentual sobre a receita prevista na assinatura', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'percent', 'value' => '10', 'base' => 'expected_revenue', 'trigger_event' => 'contract_signed'])->id]);

    sign(opportunityFor($indicator), '3500.00', '3000.00');

    expect(Commission::firstOrFail()->expected_amount)->toBe('300.00')
        ->and(Commission::first()->base_amount)->toBe('3000.00');
});

it('gera percentual a cada recebimento quando a base é a receita recebida', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'percent', 'value' => '5', 'base' => 'received_revenue', 'trigger_event' => 'revenue_received'])->id]);
    $contract = sign(opportunityFor($indicator));

    expect(Commission::count())->toBe(0);

    app(RegisterContract::class)->registerReceipt($contract, ['received_at' => now()->toDateString(), 'amount' => '1000.00'], $this->manager);
    app(RegisterContract::class)->registerReceipt($contract, ['received_at' => now()->toDateString(), 'amount' => '333.33'], $this->manager);

    expect(Commission::pluck('expected_amount')->all())->toBe(['50.00', '16.67']);
});

it('valor fixo no recebimento gera uma única vez', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'fixed', 'value' => '200', 'trigger_event' => 'revenue_received'])->id]);
    $contract = sign(opportunityFor($indicator));

    app(RegisterContract::class)->registerReceipt($contract, ['received_at' => now()->toDateString(), 'amount' => '1000'], $this->manager);
    app(RegisterContract::class)->registerReceipt($contract, ['received_at' => now()->toDateString(), 'amount' => '1000'], $this->manager);

    expect(Commission::count())->toBe(1);
});

it('não gera comissão sem indicador, sem regra ou com regra inativa', function () {
    sign(opportunityFor(null));
    sign(opportunityFor(RegionalIndicator::factory()->create()));
    sign(opportunityFor(RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'fixed', 'value' => '100', 'trigger_event' => 'contract_signed', 'active' => false])->id])));

    expect(Commission::count())->toBe(0);
});

it('mudar a regra depois não altera a comissão já gerada', function () {
    $r = rule(['type' => 'fixed', 'value' => '150.00', 'trigger_event' => 'contract_signed']);
    sign(opportunityFor(RegionalIndicator::factory()->create(['commission_rule_id' => $r->id])));

    $r->update(['value' => '999.00']);

    expect(Commission::first()->expected_amount)->toBe('150.00')
        ->and(Commission::first()->rule_snapshot)->toContain('150,00');
});

it('aprova, paga em parcelas e quita', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'fixed', 'value' => '150.00', 'trigger_event' => 'contract_signed'])->id]);
    sign(opportunityFor($indicator));
    $c = Commission::firstOrFail();

    $page = Livewire::actingAs($this->manager)->test(CommissionsIndex::class);
    $page->call('open', 'approve', $c->id)->set('approve.amount', '120.00')->call('saveApprove')->assertHasNoErrors();
    expect($c->fresh()->status)->toBe(CommissionStatus::Approved)->and($c->fresh()->approved_amount)->toBe('120.00');

    $page->call('open', 'payment', $c->id)->set('payment.amount', '200')->call('savePayment')->assertHasErrors('payment.amount');
    $page->call('open', 'payment', $c->id)->set('payment.amount', '70')->call('savePayment')->assertHasNoErrors();
    expect($c->fresh()->status)->toBe(CommissionStatus::Approved);

    $page->call('open', 'payment', $c->id)->assertSet('payment.amount', '50.00')->call('savePayment')->assertHasNoErrors();
    expect($c->fresh()->status)->toBe(CommissionStatus::Paid);
});

it('não paga comissão sem aprovação e não cancela comissão já paga em parte', function () {
    $indicator = RegionalIndicator::factory()->create(['commission_rule_id' => rule(['type' => 'fixed', 'value' => '100', 'trigger_event' => 'contract_signed'])->id]);
    sign(opportunityFor($indicator));
    $c = Commission::firstOrFail();
    $action = app(Commissions::class);

    expect(fn () => $action->pay($c, '10', now()->toDateString(), $this->manager))->toThrow(ValidationException::class);

    $action->approve($c, '100', $this->manager);
    $action->pay($c->fresh(), '10', now()->toDateString(), $this->manager);

    expect(fn () => $action->cancel($c->fresh(), 'erro'))->toThrow(ValidationException::class);
});

it('recusa regra percentual sobre receita recebida gerada na assinatura', function () {
    Livewire::actingAs($this->manager)->test(CommissionRules::class)
        ->set('form.name', 'Inválida')
        ->set('form.type', 'percent')
        ->set('form.value', '5')
        ->set('form.base', 'received_revenue')
        ->set('form.trigger_event', 'contract_signed')
        ->call('save')
        ->assertHasErrors('form.trigger_event');
});
