<?php

use App\Actions\Crm\RegisterContract;
use App\Actions\Finance\ReviewExpense;
use App\Enums\CommissionStatus;
use App\Enums\ContractStatus;
use App\Enums\ExpenseStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Livewire\Finance\Dashboard;
use App\Livewire\Finance\Expenses;
use App\Metrics\FinanceMetrics;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Commission;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Region;
use App\Models\RegionalIndicator;
use App\Models\SystemSetting;
use App\Models\Visit;
use App\Notifications\ExpenseRejected;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, SystemSettingsSeeder::class]);
    $this->manager = userWithRole(Role::Manager);
    $this->region = Region::create(['name' => 'Cariri']);
    $this->exec = Executive::factory()->create(['region_id' => $this->region->id]);
    $this->exec->user->assignRole('executivo');
    $this->fuel = ExpenseCategory::where('slug', 'combustivel-e-deslocamento')->firstOrFail();
    $this->food = ExpenseCategory::where('slug', 'alimentacao')->firstOrFail();
    $this->team = ExpenseCategory::where('slug', 'equipe-comercial')->firstOrFail();
});

function expense(array $attrs, ExpenseStatus $status = ExpenseStatus::Approved): Expense
{
    $e = new Expense(array_merge(['description' => 'x', 'spent_on' => now()->toDateString(), 'executive_id' => test()->exec->id], $attrs));
    $e->region_id = array_key_exists('region_id', $attrs) ? $attrs['region_id'] : test()->region->id;
    $e->status = $status;
    $e->save();

    return $e;
}

function signedContract(string $value, ?string $expected, string $date): Contract
{
    $lead = Lead::factory()->create(['executive_id' => test()->exec->id]);
    $o = Opportunity::factory()->create(['lead_id' => $lead->id, 'executive_id' => test()->exec->id]);

    return app(RegisterContract::class)->handle($o, [
        'status' => ContractStatus::Signed, 'signed_at' => $date, 'contract_value' => $value, 'expected_revenue' => $expected,
    ], null);
}

it('o gestor aprova; ninguém aprova a própria despesa; recusar exige motivo e avisa', function () {
    Notification::fake();
    $own = expense(['expense_category_id' => $this->fuel->id, 'amount' => '100'], ExpenseStatus::Pending);
    $other = expense(['expense_category_id' => $this->fuel->id, 'amount' => '50'], ExpenseStatus::Pending);

    Livewire::actingAs($this->exec->user)->test(Expenses::class)->call('approve', $own->id);
    expect($own->fresh()->status)->toBe(ExpenseStatus::Pending);

    $page = Livewire::actingAs($this->manager)->test(Expenses::class);
    $page->call('approve', $own->id);
    expect($own->fresh()->status)->toBe(ExpenseStatus::Approved)->and($own->fresh()->approved_by)->toBe($this->manager->id);

    $page->call('openReject', $other->id)->call('reject')->assertHasErrors('rejectionReason');
    $page->set('rejectionReason', 'Sem comprovante')->call('reject')->assertHasNoErrors();
    expect($other->fresh()->status)->toBe(ExpenseStatus::Rejected);
    Notification::assertSentTo($this->exec->user, ExpenseRejected::class);

    expect(fn () => app(ReviewExpense::class)->approve($other->fresh(), $this->manager))
        ->toThrow(ValidationException::class);
});

it('aprova em lote só o que o usuário pode aprovar', function () {
    $managerExec = Executive::factory()->create(['user_id' => $this->manager->id]);
    $a = expense(['expense_category_id' => $this->fuel->id, 'amount' => '10'], ExpenseStatus::Pending);
    $b = expense(['expense_category_id' => $this->fuel->id, 'amount' => '20', 'executive_id' => $managerExec->id], ExpenseStatus::Pending);

    Livewire::actingAs($this->manager)->test(Expenses::class)
        ->set('selected', [$a->id, $b->id])
        ->call('approveSelected');

    expect($a->fresh()->status)->toBe(ExpenseStatus::Approved)
        ->and($b->fresh()->status)->toBe(ExpenseStatus::Pending);
});

it('calcula custos só com despesas efetivas aprovadas, por visita e por contrato', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $period = Period::custom('2026-11-01', '2026-11-30');

    expense(['expense_category_id' => $this->fuel->id, 'amount' => '300.00', 'spent_on' => '2026-11-05']);
    expense(['expense_category_id' => $this->food->id, 'amount' => '100.00', 'spent_on' => '2026-11-06']);
    expense(['expense_category_id' => $this->fuel->id, 'amount' => '999.00', 'spent_on' => '2026-11-06'], ExpenseStatus::Pending);
    expense(['expense_category_id' => $this->fuel->id, 'amount' => '888.00', 'spent_on' => '2026-11-06'], ExpenseStatus::Rejected);
    expense(['expense_category_id' => $this->fuel->id, 'amount' => '777.00', 'spent_on' => '2026-10-30']);

    Visit::factory()->count(4)->create(['executive_id' => $this->exec->id, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-11-10 09:00']);
    signedContract('3000.00', '2500.00', '2026-11-12');
    signedContract('1000.00', null, '2026-11-15');

    $s = (new FinanceMetrics($period))->summary();

    expect($s['operational'])->toBe('400.00')
        ->and($s['travel'])->toBe('300.00')
        ->and($s['pending'])->toBe('999.00')
        ->and($s['pending_count'])->toBe(1)
        ->and($s['cost_per_visit'])->toBe('100.00')
        ->and($s['cost_per_contract'])->toBe('200.00')
        ->and($s['contracted'])->toBe('4000.00')
        ->and($s['expected_revenue'])->toBe('3500.00')
        ->and($s['received'])->toBe('0.00');
});

it('calcula a margem pela receita prevista ou recebida, menos variáveis e comissões', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $period = Period::custom('2026-11-01', '2026-11-30');

    expense(['expense_category_id' => $this->fuel->id, 'amount' => '200.00', 'spent_on' => '2026-11-05']);
    expense(['expense_category_id' => $this->team->id, 'amount' => '5000.00', 'spent_on' => '2026-11-05']); // fixa: fora da margem
    $contract = signedContract('3000.00', '2500.00', '2026-11-12');
    app(RegisterContract::class)->registerReceipt($contract, ['received_at' => '2026-11-18', 'amount' => '1000.00'], null);

    $c = Commission::create(['regional_indicator_id' => RegionalIndicator::factory()->create()->id, 'contract_id' => $contract->id,
        'rule_snapshot' => 'x', 'expected_amount' => '150.00', 'generated_at' => '2026-11-12']);
    Commission::create(['regional_indicator_id' => $c->regional_indicator_id, 'contract_id' => signedContract('10', null, '2026-11-13')->id,
        'rule_snapshot' => 'x', 'expected_amount' => '999.00', 'generated_at' => '2026-11-13'])->forceFill(['status' => CommissionStatus::Cancelled])->save();

    $s = (new FinanceMetrics($period))->summary();
    expect($s['variable_costs'])->toBe('200.00')
        ->and($s['commissions'])->toBe('150.00')
        ->and($s['margin_revenue'])->toBe('2510.00')
        ->and($s['margin'])->toBe('2160.00');

    SystemSetting::put('finance.margin_revenue_basis', 'received');
    $s = (new FinanceMetrics($period))->summary();
    expect($s['margin_revenue'])->toBe('1000.00')
        ->and($s['margin'])->toBe('650.00');
});

it('separa por categoria, região, executivo e mês', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $period = Period::custom('2026-11-01', '2026-11-30');
    $other = Executive::factory()->create();

    expense(['expense_category_id' => $this->fuel->id, 'amount' => '300', 'spent_on' => '2026-11-05']);
    expense(['expense_category_id' => $this->food->id, 'amount' => '100', 'spent_on' => '2026-11-05', 'executive_id' => $other->id, 'region_id' => null]);
    Visit::factory()->count(2)->create(['executive_id' => $this->exec->id, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-11-10 09:00']);

    $m = new FinanceMetrics($period);
    expect(collect($m->byCategory())->pluck('total', 'category')->all())->toBe(['Combustível e deslocamento' => '300.00', 'Alimentação' => '100.00'])
        ->and(collect($m->byRegion())->firstWhere('region', 'Cariri')['cost_per_visit'])->toBe('150.00')
        ->and(collect($m->byExecutive())->firstWhere('executive.id', $this->exec->id)['cost'])->toBe('300.00')
        ->and($m->monthly()['labels'])->toHaveCount(6)
        ->and(end($m->monthly()['costs']))->toBe(400.0);

    expect((new FinanceMetrics($period, null, $this->region->id))->summary()['operational'])->toBe('300.00')
        ->and((new FinanceMetrics($period, [$other->id]))->summary()['operational'])->toBe('100.00');
});

it('o executivo vê só as próprias despesas e não acessa o painel financeiro', function () {
    $other = Executive::factory()->create();
    expense(['expense_category_id' => $this->fuel->id, 'amount' => '10', 'description' => 'Minha despesa']);
    expense(['expense_category_id' => $this->fuel->id, 'amount' => '20', 'description' => 'Despesa de outro', 'executive_id' => $other->id]);

    $this->actingAs($this->exec->user)->get(route('finance.index'))->assertRedirect(route('finance.expenses'));
    $this->actingAs($this->exec->user)->get(route('finance.dashboard'))->assertForbidden();

    Livewire::actingAs($this->exec->user)->test(Expenses::class)
        ->assertSee('Minha despesa')
        ->assertDontSee('Despesa de outro');

    $this->actingAs($this->manager)->get(route('finance.index'))->assertRedirect(route('finance.dashboard'));
    Livewire::actingAs($this->manager)->test(Dashboard::class)->assertOk();
});
