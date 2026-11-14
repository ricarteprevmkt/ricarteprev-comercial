<?php

namespace App\Livewire\Funnel;

use App\Actions\Crm\SaveLead;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\OpportunityStage;
use App\Livewire\Concerns\LoadsLocationOptions;
use App\Models\Catalogs\LeadSource;
use App\Models\Lead;
use App\Models\RegionalIndicator;
use App\Rules\BrazilianPhone;
use App\Rules\ValidDocument;
use App\Support\Document;
use App\Support\Phone;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de cadastro rápido de contato, usado nas listas de contatos e de
 * oportunidades. Poucos campos obrigatórios: nome, telefone e responsável.
 */
class LeadForm extends Component
{
    use LoadsLocationOptions;

    public ?int $leadId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $openOpportunity = true;

    /** @var array<string, mixed> */
    public array $opportunity = [];

    public bool $allowSamePhone = false;

    public bool $samePhoneWarning = false;

    public function mount(): void
    {
        $this->resetFields();
    }

    #[On('lead-form:create')]
    public function create(): void
    {
        abort_unless(auth()->user()->can('leads.manage'), 403);
        $this->resetFields();
        Flux::modal('lead-form')->show();
    }

    #[On('lead-form:edit')]
    public function edit(int $id): void
    {
        $lead = Lead::query()->findOrFail($id);
        $this->authorize('update', $lead);
        $this->resetFields();
        $this->leadId = $lead->id;
        $this->openOpportunity = false;
        $this->form = [
            'type' => $lead->type->value,
            'name' => $lead->name,
            'document' => Document::format($lead->document()),
            'phone' => Phone::format($lead->phone),
            'whatsapp' => Phone::format($lead->whatsapp),
            'email' => $lead->email ?? '',
            'city_id' => $lead->city_id,
            'neighborhood' => $lead->neighborhood ?? '',
            'address' => $lead->address ?? '',
            'lead_source_id' => $lead->lead_source_id,
            'executive_id' => $lead->executive_id,
            'regional_indicator_id' => $lead->regional_indicator_id,
            'entered_at' => $lead->entered_at->toDateString(),
            'status' => $lead->status->value,
            'notes' => $lead->notes ?? '',
        ];
        Flux::modal('lead-form')->show();
    }

    public function save(SaveLead $action): void
    {
        $user = auth()->user();
        $lead = $this->leadId ? Lead::query()->findOrFail($this->leadId) : null;
        $lead ? $this->authorize('update', $lead) : abort_unless($user->can('leads.manage'), 403);

        $type = LeadType::tryFrom((string) $this->form['type']) ?? LeadType::Person;
        $allowedExecutives = Visibility::assignableExecutives($user, $lead?->executive_id)->pluck('id')->all();

        $rules = [
            'form.type' => ['required', new Enum(LeadType::class)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.document' => ['nullable', new ValidDocument($type)],
            'form.phone' => ['required_without:form.whatsapp', 'nullable', new BrazilianPhone],
            'form.whatsapp' => ['nullable', new BrazilianPhone],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'form.neighborhood' => ['nullable', 'string', 'max:120'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.lead_source_id' => ['required', 'integer', 'exists:lead_sources,id'],
            'form.executive_id' => ['required', 'integer', 'in:'.implode(',', $allowedExecutives ?: [0])],
            'form.regional_indicator_id' => ['nullable', 'integer', 'exists:regional_indicators,id'],
            'form.entered_at' => ['required', 'date', 'before_or_equal:today'],
            'form.status' => ['required', new Enum(LeadStatus::class)],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->openOpportunity) {
            $rules += [
                'opportunity.stage' => ['required', 'in:contact,qualified,scheduled,proposal'],
                'opportunity.interest' => ['nullable', 'string', 'max:255'],
                'opportunity.estimated_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
                'opportunity.next_action' => ['nullable', 'string', 'max:255'],
                'opportunity.next_action_due' => ['nullable', 'date'],
            ];
        }

        $this->validate($rules, [
            'form.phone.required_without' => 'Informe o telefone ou o WhatsApp.',
            'form.executive_id.in' => 'Escolha um executivo responsável válido.',
        ], [
            'form.name' => 'nome', 'form.document' => $type->documentLabel(), 'form.phone' => 'telefone',
            'form.whatsapp' => 'WhatsApp', 'form.email' => 'e-mail', 'form.city_id' => 'cidade',
            'form.lead_source_id' => 'origem', 'form.executive_id' => 'executivo responsável',
            'form.entered_at' => 'data de entrada', 'opportunity.stage' => 'etapa inicial',
            'opportunity.estimated_value' => 'valor estimado', 'opportunity.next_action_due' => 'prazo',
        ]);

        $data = collect($this->form)->except('document')
            ->map(fn ($v) => $v === '' ? null : $v)
            ->all();

        try {
            $result = $action->handle(
                $lead,
                $data,
                $this->form['document'] ?: null,
                $this->openOpportunity ? [
                    'stage' => OpportunityStage::from($this->opportunity['stage']),
                    'interest' => $this->opportunity['interest'] ?: null,
                    'estimated_value' => $this->opportunity['estimated_value'] !== '' ? $this->opportunity['estimated_value'] : null,
                    'next_action' => $this->opportunity['next_action'] ?: null,
                    'next_action_due' => $this->opportunity['next_action_due'] ?: null,
                ] : null,
                $user,
                $this->allowSamePhone,
            );
        } catch (ValidationException $e) {
            $this->samePhoneWarning = array_key_exists('form.phone', $e->errors());
            $this->setErrorBag($e->errors());

            return;
        }

        Flux::modal('lead-form')->close();
        Flux::toast(variant: 'success', text: $lead ? 'Contato atualizado.' : ($result['opportunity'] ? 'Contato cadastrado e oportunidade aberta.' : 'Contato cadastrado.'));
        $this->dispatch('lead-saved');

        if ($result['opportunity']) {
            $this->redirectRoute('funnel.opportunities.show', $result['opportunity'], navigate: true);
        }

        $this->resetFields();
    }

    private function resetFields(): void
    {
        $this->resetValidation();
        $this->leadId = null;
        $this->openOpportunity = true;
        $this->allowSamePhone = false;
        $this->samePhoneWarning = false;
        $ownExecutive = auth()->user()?->executive()->value('id');
        $this->form = [
            'type' => LeadType::Person->value, 'name' => '', 'document' => '', 'phone' => '', 'whatsapp' => '', 'email' => '',
            'city_id' => null, 'neighborhood' => '', 'address' => '', 'lead_source_id' => null,
            'executive_id' => $ownExecutive, 'regional_indicator_id' => null,
            'entered_at' => now()->toDateString(), 'status' => LeadStatus::Active->value, 'notes' => '',
        ];
        $this->opportunity = ['stage' => OpportunityStage::Contact->value, 'interest' => '', 'estimated_value' => '', 'next_action' => '', 'next_action_due' => ''];
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.funnel.lead-form', [
            'types' => LeadType::cases(),
            'statuses' => LeadStatus::cases(),
            'cities' => $this->cityOptions($this->form['city_id'] ? (int) $this->form['city_id'] : null),
            'sources' => LeadSource::query()->active()->ordered()->get(),
            'executives' => Visibility::assignableExecutives($user, $this->form['executive_id'] ? (int) $this->form['executive_id'] : null),
            'indicators' => RegionalIndicator::query()->active()->orderBy('name')->get(['id', 'name', 'city_id']),
            'initialStages' => [OpportunityStage::Contact, OpportunityStage::Qualified, OpportunityStage::Scheduled, OpportunityStage::Proposal],
        ]);
    }
}
