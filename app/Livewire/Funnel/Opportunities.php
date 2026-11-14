<?php

namespace App\Livewire\Funnel;

use App\Enums\OpportunityStage;
use App\Models\Catalogs\LeadSource;
use App\Models\Opportunity;
use App\Models\SystemSetting;
use App\Support\Visibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Oportunidades')]
class Opportunities extends Component
{
    use WithPagination;

    #[Url(as: 'busca', except: '')]
    public string $search = '';

    /** abertas | todas | atrasadas | paradas | uma etapa */
    #[Url(as: 'etapa', except: 'abertas')]
    public string $stageFilter = 'abertas';

    #[Url(as: 'origem', except: '')]
    public string $sourceFilter = '';

    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Opportunity::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'stageFilter', 'sourceFilter', 'executiveFilter'], true)) {
            $this->resetPage();
        }
    }

    #[On('lead-saved')]
    public function refreshList(): void
    {
        unset($this->opportunities);
    }

    /** @return LengthAwarePaginator<int, Opportunity> */
    #[Computed]
    public function opportunities(): LengthAwarePaginator
    {
        $query = Opportunity::query()
            ->visibleTo(auth()->user())
            ->with(['lead.city', 'executive.user', 'source'])
            ->join('leads', 'leads.id', '=', 'opportunities.lead_id')
            ->select('opportunities.*')
            ->when($this->search !== '', fn ($q) => $q->where('leads.name', 'like', "%{$this->search}%"))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('opportunities.lead_source_id', $this->sourceFilter))
            ->when($this->executiveFilter !== '', fn ($q) => $q->where('opportunities.executive_id', $this->executiveFilter));

        match ($this->stageFilter) {
            'todas' => null,
            'abertas' => $query->open(),
            'atrasadas' => $query->overdue(),
            'paradas' => $query->stale(),
            default => OpportunityStage::tryFrom($this->stageFilter)
                ? $query->where('opportunities.stage', $this->stageFilter)
                : $query->open(),
        };

        // Prazos vencidos primeiro, depois os mais próximos, depois sem prazo.
        return $query
            ->orderByRaw('opportunities.next_action_due is null')
            ->orderBy('opportunities.next_action_due')
            ->latest('opportunities.last_activity_at')
            ->paginate(20);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.funnel.opportunities', [
            'stages' => OpportunityStage::cases(),
            'sources' => LeadSource::query()->ordered()->get(),
            'executives' => $user->can('funnel.view_all') ? Visibility::assignableExecutives($user) : collect(),
            'staleDays' => (int) SystemSetting::get('funnel.stale_after_days', 7),
        ]);
    }
}
