<?php

use App\Actions\Crm\MoveOpportunity;
use App\Actions\Crm\RegisterContract;
use App\Enums\ContractStatus;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Livewire\Funnel\Contracts;
use App\Livewire\Funnel\OpportunityShow;
use App\Models\Catalogs\LossReason;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Lead;
use App\Models\Opportunity;
use Database\Seeders\CatalogSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CatalogSeeder::class);
    $this->manager = userWithRole(Role::Manager);
    $this->executive = Executive::factory()->create();
    $this->executive->user->assignRole('executivo');
    $this->opportunity = Opportunity::factory()->create(['executive_id' => $this->executive->id, 'lead_id' => Lead::factory()->create(['executive_id' => $this->executive->id])->id]);
});

it('move a etapa, registra o histórico e guarda a etapa mais avançada', function () {
    $show = Livewire::actingAs($this->executive->user)->test(OpportunityShow::class, ['opportunity' => $this->opportunity]);

    $show->call('openMove', 'scheduled')->set('move.note', 'Atendimento marcado')->call('saveMove')->assertHasNoErrors();
    $show->call('openMove', 'follow_up')->call('saveMove')->assertHasNoErrors();

    $o = $this->opportunity->fresh();
    expect($o->stage)->toBe(OpportunityStage::FollowUp)
        ->and($o->furthest_stage_rank)->toBe(3)
        ->and($o->histories()->pluck('to_stage')->map->value->all())->toBe(['follow_up', 'scheduled']);
});

it('exige motivo para perder e limpa o motivo ao reabrir', function () {
    $reason = LossReason::where('slug', 'ausencia-de-interesse')->firstOrFail();
    $show = Livewire::actingAs($this->executive->user)->test(OpportunityShow::class, ['opportunity' => $this->opportunity]);

    $show->call('openMove', 'lost')->call('saveMove')->assertHasErrors('move.loss_reason_id');

    $show->call('openMove', 'lost')->set('move.loss_reason_id', $reason->id)->call('saveMove')->assertHasNoErrors();
    $o = $this->opportunity->fresh();
    expect($o->stage)->toBe(OpportunityStage::Lost)->and($o->loss_reason_id)->toBe($reason->id)->and($o->closed_at)->not->toBeNull();

    $show->call('openMove', 'qualified')->call('saveMove')->assertHasNoErrors();
    $o->refresh();
    expect($o->loss_reason_id)->toBeNull()->and($o->closed_at)->toBeNull();
});

it('não permite marcar contrato assinado sem registrar o contrato', function () {
    expect(fn () => app(MoveOpportunity::class)->handle($this->opportunity, OpportunityStage::Signed, $this->manager))
        ->toThrow(ValidationException::class);
});

it('registrar proposta avança para proposta apresentada', function () {
    Livewire::actingAs($this->executive->user)->test(OpportunityShow::class, ['opportunity' => $this->opportunity])
        ->set('proposal.amount', '2000')
        ->call('saveProposal')
        ->assertHasNoErrors();

    $o = $this->opportunity->fresh();
    expect($o->stage)->toBe(OpportunityStage::Proposal)->and($o->proposals()->count())->toBe(1);
});

it('contrato assinado fecha a oportunidade, torna o contato cliente e trava a etapa', function () {
    Livewire::actingAs($this->executive->user)->test(OpportunityShow::class, ['opportunity' => $this->opportunity])
        ->set('contract.status', 'signed')
        ->set('contract.signed_at', now()->toDateString())
        ->set('contract.contract_value', '3500')
        ->set('contract.expected_revenue', '3000')
        ->call('saveContract')
        ->assertHasNoErrors();

    $o = $this->opportunity->fresh();
    $contract = Contract::firstOrFail();

    expect($o->stage)->toBe(OpportunityStage::Signed)
        ->and($o->furthest_stage_rank)->toBe(5)
        ->and($o->lead->status)->toBe(LeadStatus::Client)
        ->and($contract->executive_id)->toBe($this->executive->id)
        ->and($contract->contract_value)->toBe('3500.00');

    expect(fn () => app(MoveOpportunity::class)->handle($o, OpportunityStage::Lost, $this->manager, lossReasonId: 1))
        ->toThrow(ValidationException::class);
});

it('contrato aguardando conclusão não muda a etapa até ser assinado', function () {
    Livewire::actingAs($this->manager)->test(OpportunityShow::class, ['opportunity' => $this->opportunity])
        ->set('contract.status', 'pending')
        ->set('contract.contract_value', '3500')
        ->call('saveContract')
        ->assertHasNoErrors();

    expect($this->opportunity->fresh()->stage)->toBe(OpportunityStage::Contact);
    $contract = Contract::firstOrFail();
    expect($contract->status)->toBe(ContractStatus::Pending)->and($contract->signed_at)->toBeNull();

    Livewire::actingAs($this->manager)->test(Contracts::class)
        ->call('openSign', $contract->id)
        ->set('signedAt', now()->toDateString())
        ->call('sign')
        ->assertHasNoErrors();

    expect($contract->fresh()->status)->toBe(ContractStatus::Signed)
        ->and($this->opportunity->fresh()->stage)->toBe(OpportunityStage::Signed);
});

it('não aceita dois contratos na mesma oportunidade', function () {
    $action = app(RegisterContract::class);
    $action->handle($this->opportunity, ['status' => ContractStatus::Pending, 'contract_value' => '100'], $this->manager);

    expect(fn () => $action->handle($this->opportunity->fresh(), ['status' => ContractStatus::Pending, 'contract_value' => '100'], $this->manager))
        ->toThrow(ValidationException::class);
});

it('registra recebimento só de contrato assinado, separado do valor contratado', function () {
    $action = app(RegisterContract::class);
    $pending = $action->handle($this->opportunity, ['status' => ContractStatus::Pending, 'contract_value' => '3500'], $this->manager);

    expect(fn () => $action->registerReceipt($pending, ['received_at' => now()->toDateString(), 'amount' => '500'], $this->manager))
        ->toThrow(ValidationException::class);

    $signed = $action->sign($pending, now()->toDateString(), $this->manager);

    Livewire::actingAs($this->manager)->test(Contracts::class)
        ->call('openReceipts', $signed->id)
        ->set('receipt.amount', '1200.50')
        ->call('saveReceipt')
        ->assertHasNoErrors()
        ->assertSee('R$ 1.200,50');

    expect($signed->receipts()->sum('amount'))->toBe('1200.50')
        ->and($signed->fresh()->contract_value)->toBe('3500.00');
});

it('o executivo não abre oportunidade de outro executivo', function () {
    $other = Opportunity::factory()->create();

    $this->actingAs($this->executive->user)->get(route('funnel.opportunities.show', $other))->assertForbidden();
    $this->actingAs($this->manager)->get(route('funnel.opportunities.show', $other))->assertOk();
});

it('o gestor sem permissão de contratos não registra contrato', function () {
    Spatie\Permission\Models\Role::findByName('gestor')->revokePermissionTo('contracts.manage');

    Livewire::actingAs($this->manager->fresh())->test(OpportunityShow::class, ['opportunity' => $this->opportunity])
        ->set('contract.contract_value', '100')
        ->call('saveContract')
        ->assertForbidden();
});
