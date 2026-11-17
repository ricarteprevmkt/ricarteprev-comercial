<?php

namespace App\Livewire\Administrative;

use App\Actions\C2\SyncC2Processes;
use App\Models\C2Process;
use App\Models\Contract;
use App\Models\IntegrationState;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O que precisa de alguém agir: clientes que o C2 parou de acompanhar,
 * exigências vencidas e contratos assinados que ainda não estão no C2.
 */
#[Title('Pendências do administrativo')]
class Pending extends Component
{
    public function render(): View
    {
        $c2Days = (int) SystemSetting::get('c2.registration_days', 2);

        return view('livewire.administrative.pending', [
            'monitoring' => C2Process::query()->inScope()->monitoringProblem()->orderBy('client_name')->get(),
            'overdue' => C2Process::query()->inScope()->openRequirement()
                ->where('requirement_deadline', '<', today()->toDateString())->orderBy('requirement_deadline')->get(),
            'missing' => IntegrationState::get(SyncC2Processes::BACKFILL_DONE, false)
                ? Contract::query()->with(['lead', 'executive.user'])->missingInC2($c2Days)->orderBy('signed_at')->get()
                : collect(),
            'c2Days' => $c2Days,
            'unmatched' => C2Process::query()->inScope()->whereNull('lead_id')->count(),
        ]);
    }
}
