<?php

namespace App\Livewire\Referrals;

use App\Enums\CommissionBase;
use App\Enums\CommissionTrigger;
use App\Enums\CommissionType;
use App\Models\CommissionRule;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Regras de comissão')]
class CommissionRules extends Component
{
    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('commissions.manage');
        $this->resetForm();
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('rule-form')->show();
    }

    public function edit(int $id): void
    {
        $rule = CommissionRule::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $rule->id;
        $this->form = [
            'name' => $rule->name,
            'type' => $rule->type->value,
            'value' => $rule->value,
            'base' => $rule->base?->value,
            'trigger_event' => $rule->trigger_event->value,
            'description' => $rule->description ?? '',
            'active' => $rule->active,
        ];
        Flux::modal('rule-form')->show();
    }

    public function save(): void
    {
        $this->authorize('commissions.manage');
        $isPercent = ($this->form['type'] ?? null) === CommissionType::Percent->value;

        $this->validate([
            'form.name' => ['required', 'string', 'max:120', Rule::unique('commission_rules', 'name')->ignore($this->editingId)],
            'form.type' => ['required', new Enum(CommissionType::class)],
            'form.value' => ['required', 'decimal:0,2', 'gt:0', $isPercent ? 'max:100' : 'max:9999999'],
            'form.base' => [$isPercent ? 'required' : 'nullable', new Enum(CommissionBase::class)],
            'form.trigger_event' => ['required', new Enum(CommissionTrigger::class)],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.active' => ['boolean'],
        ], attributes: [
            'form.name' => 'nome', 'form.type' => 'tipo', 'form.value' => 'valor', 'form.base' => 'base de cálculo',
            'form.trigger_event' => 'evento', 'form.description' => 'descrição',
        ]);

        $rule = $this->editingId ? CommissionRule::query()->findOrFail($this->editingId) : new CommissionRule;
        $rule->fill([
            'name' => $this->form['name'],
            'type' => $this->form['type'],
            'value' => $this->form['value'],
            'base' => $isPercent ? $this->form['base'] : null,
            'trigger_event' => $this->form['trigger_event'],
            'description' => $this->form['description'] ?: null,
            'active' => (bool) $this->form['active'],
        ])->save();

        Flux::modal('rule-form')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? 'Regra atualizada.' : 'Regra criada.');
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = [
            'name' => '', 'type' => CommissionType::Fixed->value, 'value' => null, 'base' => null,
            'trigger_event' => CommissionTrigger::ContractSigned->value, 'description' => '', 'active' => true,
        ];
    }

    /** @return Collection<int, CommissionRule> */
    #[Computed]
    public function rules(): Collection
    {
        return CommissionRule::query()->withCount('indicators')->orderByDesc('active')->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.referrals.commission-rules', [
            'types' => CommissionType::cases(),
            'bases' => CommissionBase::cases(),
            'triggers' => CommissionTrigger::cases(),
        ]);
    }
}
