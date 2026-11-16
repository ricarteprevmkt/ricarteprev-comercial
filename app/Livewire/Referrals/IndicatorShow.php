<?php

namespace App\Livewire\Referrals;

use App\Metrics\ReferralMetrics;
use App\Models\RegionalIndicator;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Visão individual de um parceiro: números do período, indicações e comissões.
 */
#[Title('Parceiro')]
class IndicatorShow extends Component
{
    public RegionalIndicator $indicator;

    public function mount(RegionalIndicator $indicator): void
    {
        abort_unless(auth()->user()->canAny(['indicators.manage', 'referrals.view_all']), 403);
        $this->indicator = $indicator;
    }

    #[On('period-changed')]
    public function periodChanged(): void {}

    public function render(): View
    {
        $period = Period::current();
        $metrics = new ReferralMetrics($period, indicatorId: $this->indicator->id);
        $this->indicator->load(['city', 'region', 'commissionRule', 'defaultExecutive.user', 'referralChannel']);

        return view('livewire.referrals.indicator-show', [
            'period' => $period,
            'totals' => $metrics->totals(),
            'contracts' => $metrics->contractsOriginated(),
            'commissions' => $metrics->commissions(),
            'referrals' => $this->indicator->referrals()->with(['lead.city', 'executive.user'])->latest('referred_at')->limit(30)->get(),
            'commissionList' => $this->indicator->commissions()->with('contract.lead')->withSum('payments as paid_total', 'amount')->latest('generated_at')->limit(20)->get(),
        ])->title($this->indicator->name);
    }
}
