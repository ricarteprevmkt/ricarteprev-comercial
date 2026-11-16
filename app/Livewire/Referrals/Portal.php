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
 * Área do parceiro: registrar indicações e acompanhar o andamento.
 *
 * Por proteção de dados, mostra só nome, cidade, data e situação de cada
 * indicação. Desempenho e comissões aparecem só se o administrador liberar
 * ("referrals.performance_own" e "commissions.view_own").
 */
#[Title('Minhas indicações')]
class Portal extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->can('referrals.view_own'), 403);
    }

    #[On('referral-saved')]
    public function refresh(): void {}

    public function render(): View
    {
        $user = auth()->user();
        /** @var RegionalIndicator|null $indicator */
        $indicator = $user->regionalIndicator()->first();
        $period = Period::fromPreset('ano');

        return view('livewire.referrals.portal', [
            'indicator' => $indicator,
            'referrals' => $indicator?->referrals()->with('lead:id,name,city_id', 'lead.city:id,name,uf')->latest('referred_at')->limit(50)->get() ?? collect(),
            'totals' => $indicator && $user->can('referrals.performance_own') ? (new ReferralMetrics($period, indicatorId: $indicator->id))->totals() : null,
            'commissions' => $indicator && $user->can('commissions.view_own')
                ? $indicator->commissions()->with('contract:id,lead_id', 'contract.lead:id,name')->withSum('payments as paid_total', 'amount')->latest('generated_at')->limit(20)->get()
                : null,
        ]);
    }
}
