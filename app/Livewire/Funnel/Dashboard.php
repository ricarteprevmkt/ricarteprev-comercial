<?php

namespace App\Livewire\Funnel;

use App\Metrics\FunnelMetrics;
use App\Models\Opportunity;
use App\Support\Period;
use App\Support\Visibility;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Painel do funil comercial. Todos os números saem de FunnelMetrics,
 * calculados a partir das oportunidades e contratos registrados.
 */
#[Title('Funil comercial')]
class Dashboard extends Component
{
    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Opportunity::class);
    }

    #[On('period-changed')]
    public function periodChanged(): void
    {
        // O render recalcula com o novo período salvo na sessão.
    }

    /** @return list<int>|null */
    private function scope(): ?array
    {
        $allowed = Visibility::executiveIds(auth()->user());

        if ($allowed === null && $this->executiveFilter !== '') {
            return [(int) $this->executiveFilter];
        }

        return $allowed;
    }

    public function render(): View
    {
        $user = auth()->user();
        $period = Period::current();
        $metrics = new FunnelMetrics($period, $this->scope());
        $previous = $metrics->previous();

        $funnel = $metrics->funnel();
        $prevFunnel = $previous->funnel();

        $inProgress = Opportunity::query()
            ->visibleTo($user)
            ->when($this->scope() !== null, fn ($q) => $q->whereIn('executive_id', $this->scope() ?: [0]))
            ->open()
            ->with(['lead', 'executive.user'])
            ->latest('last_activity_at')
            ->limit(6)
            ->get();

        return view('livewire.funnel.dashboard', [
            'period' => $period,
            'funnel' => $funnel,
            'prevFunnel' => $prevFunnel,
            'signed' => $metrics->contractsSigned(),
            'prevSigned' => $previous->contractsSigned(),
            'avgDays' => $metrics->averageDaysToSign(),
            'prevAvgDays' => $previous->averageDaysToSign(),
            'byExecutive' => $metrics->byExecutive(),
            'bySource' => $metrics->bySource(),
            'lossReasons' => $metrics->lossReasons(),
            'inProgress' => $inProgress,
            'executives' => $user->can('funnel.view_all') ? Visibility::assignableExecutives($user) : collect(),
        ]);
    }
}
