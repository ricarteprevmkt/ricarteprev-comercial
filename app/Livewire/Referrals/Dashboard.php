<?php

namespace App\Livewire\Referrals;

use App\Metrics\FunnelMetrics;
use App\Metrics\ReferralMetrics;
use App\Models\ReferralHistory;
use App\Models\Region;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Painel de parceiros regionais. Números calculados por ReferralMetrics a
 * partir das indicações, do funil e das comissões registradas.
 */
#[Title('Parceiros regionais')]
class Dashboard extends Component
{
    #[Url(as: 'regiao', except: '')]
    public string $regionFilter = '';

    public function mount(): void
    {
        $this->authorize('referrals.view_all');
    }

    #[On('period-changed')]
    public function periodChanged(): void
    {
        $this->dispatch('referral-map', points: $this->metrics()->mapPoints());
    }

    public function updatedRegionFilter(): void
    {
        $this->dispatch('referral-map', points: $this->metrics()->mapPoints());
    }

    private function metrics(): ReferralMetrics
    {
        return new ReferralMetrics(Period::current(), $this->regionFilter !== '' ? (int) $this->regionFilter : null);
    }

    public function render(): View
    {
        $metrics = $this->metrics();
        $previous = $metrics->previous();
        $totals = $metrics->totals();
        $prev = $previous->totals();

        return view('livewire.referrals.dashboard', [
            'period' => Period::current(),
            'totals' => $totals,
            'prev' => $prev,
            'contracts' => $metrics->contractsOriginated(),
            'prevContracts' => $previous->contractsOriginated(),
            'revenue' => $metrics->revenueOriginated(),
            'commissions' => $metrics->commissions(),
            'byCity' => $metrics->byCity(),
            'byIndicator' => $metrics->byIndicator(10),
            'byChannel' => $metrics->byChannel(),
            'mapPoints' => $metrics->mapPoints(),
            'recent' => ReferralHistory::query()
                ->with(['referral.indicator', 'referral.lead', 'user'])
                ->whereHas('referral', fn ($q) => $q->when($this->regionFilter !== '', fn ($q) => $q->whereHas('indicator', fn ($q) => $q->where('region_id', $this->regionFilter))))
                ->latest('id')->limit(6)->get(),
            'regions' => Region::query()->active()->orderBy('name')->get(),
            'delta' => fn ($a, $b) => FunnelMetrics::delta($a, $b),
        ]);
    }
}
