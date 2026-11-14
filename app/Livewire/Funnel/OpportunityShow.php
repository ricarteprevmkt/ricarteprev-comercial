<?php

namespace App\Livewire\Funnel;

use App\Actions\Crm\MoveOpportunity;
use App\Actions\Crm\RegisterContract;
use App\Actions\Crm\RegisterProposal;
use App\Enums\ContractStatus;
use App\Enums\OpportunityStage;
use App\Models\Catalogs\LossReason;
use App\Models\Opportunity;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Oportunidade')]
class OpportunityShow extends Component
{
    public Opportunity $opportunity;

    /** @var array{stage: string, note: string, loss_reason_id: int|string|null} */
    public array $move = ['stage' => '', 'note' => '', 'loss_reason_id' => null];

    /** @var array<string, mixed> */
    public array $details = [];

    /** @var array<string, mixed> */
    public array $proposal = [];

    /** @var array<string, mixed> */
    public array $contract = [];

    public function mount(Opportunity $opportunity): void
    {
        $this->authorize('view', $opportunity);
        $this->opportunity = $opportunity;
        $this->resetForms();
    }

    private function resetForms(): void
    {
        $o = $this->opportunity;
        $this->move = ['stage' => '', 'note' => '', 'loss_reason_id' => null];
        $this->details = [
            'interest' => $o->interest ?? '',
            'estimated_value' => $o->estimated_value ?? '',
            'next_action' => $o->next_action ?? '',
            'next_action_due' => $o->next_action_due?->toDateString() ?? '',
        ];
        $this->proposal = ['presented_at' => now()->toDateString(), 'amount' => $o->estimated_value ?? '', 'description' => ''];
        $this->contract = [
            'number' => '', 'status' => ContractStatus::Signed->value, 'signed_at' => now()->toDateString(),
            'contract_value' => $o->estimated_value ?? '', 'expected_revenue' => '', 'notes' => '',
        ];
    }

    public function openMove(string $stage): void
    {
        $this->authorize('update', $this->opportunity);
        $this->resetValidation();
        $this->move = ['stage' => $stage, 'note' => '', 'loss_reason_id' => null];
        Flux::modal('move-stage')->show();
    }

    public function saveMove(MoveOpportunity $action): void
    {
        $this->authorize('update', $this->opportunity);
        $this->validate([
            'move.stage' => ['required', Rule::in(array_map(fn ($s) => $s->value, OpportunityStage::movable()))],
            'move.note' => ['nullable', 'string', 'max:1000'],
            'move.loss_reason_id' => ['nullable', 'integer', 'exists:loss_reasons,id'],
        ], attributes: ['move.stage' => 'etapa', 'move.note' => 'observação', 'move.loss_reason_id' => 'motivo']);

        try {
            $action->handle(
                $this->opportunity,
                OpportunityStage::from($this->move['stage']),
                auth()->user(),
                $this->move['note'] ?: null,
                $this->move['loss_reason_id'] ? (int) $this->move['loss_reason_id'] : null,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag(collect($e->errors())->mapWithKeys(fn ($m, $k) => [$k === 'lossReasonId' ? 'move.loss_reason_id' : 'move.stage' => $m])->all());

            return;
        }

        Flux::modal('move-stage')->close();
        Flux::toast(variant: 'success', text: 'Etapa atualizada para '.$this->opportunity->stage->label().'.');
        $this->opportunity->refresh();
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->opportunity);
        $this->validate([
            'details.interest' => ['nullable', 'string', 'max:255'],
            'details.estimated_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'details.next_action' => ['nullable', 'string', 'max:255'],
            'details.next_action_due' => ['nullable', 'date'],
        ], attributes: ['details.estimated_value' => 'valor estimado', 'details.next_action_due' => 'prazo']);

        $this->opportunity->fill([
            'interest' => $this->details['interest'] ?: null,
            'estimated_value' => $this->details['estimated_value'] !== '' ? $this->details['estimated_value'] : null,
            'next_action' => $this->details['next_action'] ?: null,
            'next_action_due' => $this->details['next_action_due'] ?: null,
        ]);
        $this->opportunity->last_activity_at = now();
        $this->opportunity->save();

        Flux::toast(variant: 'success', text: 'Próxima ação e dados atualizados.');
    }

    public function saveProposal(RegisterProposal $action): void
    {
        $this->authorize('update', $this->opportunity);
        $this->validate([
            'proposal.presented_at' => ['required', 'date', 'before_or_equal:today'],
            'proposal.amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'proposal.description' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['proposal.presented_at' => 'data', 'proposal.amount' => 'valor', 'proposal.description' => 'descrição']);

        try {
            $action->handle($this->opportunity, [
                'presented_at' => $this->proposal['presented_at'],
                'amount' => $this->proposal['amount'] !== '' ? $this->proposal['amount'] : null,
                'description' => $this->proposal['description'] ?: null,
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('proposal-form')->close();
        Flux::toast(variant: 'success', text: 'Proposta registrada.');
        $this->opportunity->refresh();
        $this->resetForms();
    }

    public function saveContract(RegisterContract $action): void
    {
        $this->authorize('registerContract', $this->opportunity);
        $signed = $this->contract['status'] === ContractStatus::Signed->value;

        $this->validate([
            'contract.number' => ['nullable', 'string', 'max:50', Rule::unique('contracts', 'number')],
            'contract.status' => ['required', new Enum(ContractStatus::class), Rule::in(['pending', 'signed'])],
            'contract.signed_at' => [$signed ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'contract.contract_value' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'contract.expected_revenue' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'contract.notes' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'contract.number' => 'número', 'contract.signed_at' => 'data de assinatura',
            'contract.contract_value' => 'valor do contrato', 'contract.expected_revenue' => 'receita prevista',
        ]);

        try {
            $action->handle($this->opportunity, [
                'number' => $this->contract['number'] ?: null,
                'status' => ContractStatus::from($this->contract['status']),
                'signed_at' => $this->contract['signed_at'] ?: null,
                'contract_value' => $this->contract['contract_value'],
                'expected_revenue' => $this->contract['expected_revenue'] !== '' ? $this->contract['expected_revenue'] : null,
                'notes' => $this->contract['notes'] ?: null,
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('contract-form')->close();
        Flux::toast(variant: 'success', text: $signed ? 'Contrato assinado registrado.' : 'Contrato registrado como aguardando conclusão.');
        $this->opportunity->refresh();
    }

    #[On('lead-saved')]
    #[On('visit-saved')]
    public function refreshLead(): void
    {
        $this->opportunity->refresh();
    }

    public function render(): View
    {
        $this->opportunity->load(['lead.city', 'lead.indicator', 'executive.user', 'source', 'indicator', 'lossReason', 'contract', 'proposals', 'histories.user', 'visits']);

        return view('livewire.funnel.opportunity-show', [
            'funnel' => OpportunityStage::funnel(),
            'movable' => OpportunityStage::movable(),
            'lossReasons' => LossReason::query()->active()->whereIn('applies_to', ['opportunity', 'both'])->ordered()->get(),
            'canUpdate' => auth()->user()->can('update', $this->opportunity),
            'canContract' => auth()->user()->can('registerContract', $this->opportunity),
        ])->title($this->opportunity->lead->name);
    }
}
