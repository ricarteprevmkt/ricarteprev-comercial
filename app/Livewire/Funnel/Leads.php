<?php

namespace App\Livewire\Funnel;

use App\Actions\Crm\SaveLead;
use App\Enums\LeadStatus;
use App\Enums\OpportunityStage;
use App\Models\Catalogs\LeadSource;
use App\Models\Lead;
use App\Support\Phone;
use App\Support\Visibility;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Contatos')]
class Leads extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    #[Url(as: 'situacao', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'origem', except: '')]
    public string $sourceFilter = '';

    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    public ?int $openFor = null;

    /** @var array<string, mixed> */
    public array $opportunity = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'sourceFilter', 'executiveFilter'], true)) {
            $this->resetPage();
        }
    }

    #[On('lead-saved')]
    public function refreshList(): void
    {
        unset($this->leads);
    }

    public function startOpportunity(int $leadId): void
    {
        $lead = Lead::query()->findOrFail($leadId);
        $this->authorize('update', $lead);
        $this->resetValidation();
        $this->openFor = $lead->id;
        $this->opportunity = ['stage' => OpportunityStage::Contact->value, 'interest' => '', 'estimated_value' => '', 'next_action' => '', 'next_action_due' => ''];
        Flux::modal('open-opportunity')->show();
    }

    public function createOpportunity(SaveLead $action): void
    {
        $lead = Lead::query()->findOrFail($this->openFor);
        $this->authorize('update', $lead);

        $this->validate([
            'opportunity.stage' => ['required', 'in:contact,qualified,scheduled,proposal'],
            'opportunity.interest' => ['nullable', 'string', 'max:255'],
            'opportunity.estimated_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'opportunity.next_action' => ['nullable', 'string', 'max:255'],
            'opportunity.next_action_due' => ['nullable', 'date'],
        ], attributes: ['opportunity.stage' => 'etapa inicial', 'opportunity.estimated_value' => 'valor estimado']);

        try {
            $opportunity = $action->openOpportunity($lead, [
                'stage' => OpportunityStage::from($this->opportunity['stage']),
                'interest' => $this->opportunity['interest'] ?: null,
                'estimated_value' => $this->opportunity['estimated_value'] !== '' ? $this->opportunity['estimated_value'] : null,
                'next_action' => $this->opportunity['next_action'] ?: null,
                'next_action_due' => $this->opportunity['next_action_due'] ?: null,
            ], auth()->user());
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->redirectRoute('funnel.opportunities.show', $opportunity, navigate: true);
    }

    /** @return LengthAwarePaginator<int, Lead> */
    #[Computed]
    public function leads(): LengthAwarePaginator
    {
        $digits = Phone::normalize($this->search);

        return Lead::query()
            ->visibleTo(auth()->user())
            ->with(['city', 'source', 'executive.user', 'indicator'])
            ->withCount(['opportunities as open_opportunities_count' => fn ($q) => $q->open()])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->when($digits && strlen($digits) >= 4, fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%")->orWhere('whatsapp', 'like', "%{$digits}%"))))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('lead_source_id', $this->sourceFilter))
            ->when($this->executiveFilter !== '', fn ($q) => $q->where('executive_id', $this->executiveFilter))
            ->latest('entered_at')->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.funnel.leads', [
            'statuses' => LeadStatus::cases(),
            'sources' => LeadSource::query()->ordered()->get(),
            'executives' => $user->can('funnel.view_all') ? Visibility::assignableExecutives($user) : collect(),
            'initialStages' => [OpportunityStage::Contact, OpportunityStage::Qualified, OpportunityStage::Scheduled, OpportunityStage::Proposal],
        ]);
    }
}
