<?php

namespace App\Livewire\Administrative;

use App\Actions\C2\SyncC2Processes;
use App\Enums\InssOutcome;
use App\Metrics\FunnelMetrics;
use App\Metrics\InssDeadlines;
use App\Models\C2Process;
use App\Models\IntegrationState;
use App\Services\C2\C2Client;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Painel do administrativo: o resumo do dia dos processos no INSS, lidos do
 * C2 Prev. Só consulta.
 */
#[Title('Painel administrativo')]
class Dashboard extends Component
{
    public function render(): View
    {
        $today = today()->toDateString();
        $in7 = today()->addDays(7)->toDateString();
        $base = fn () => C2Process::query()->inScope();

        $since = today()->subDays(30)->toDateString();
        $granted = $base()->where('outcome', InssOutcome::Granted->value)->where('concluded_at', '>=', $since)->count();
        $denied = $base()->where('outcome', InssOutcome::Denied->value)->where('concluded_at', '>=', $since)->count();

        $byBenefit = $base()
            ->selectRaw("coalesce(benefit_type, 'Não informado') as label, outcome, count(*) as total")
            ->groupBy('label', 'outcome')->get()
            ->groupBy('label')
            ->map(function ($rows, $label) {
                $count = fn (InssOutcome $o) => (int) $rows->firstWhere('outcome', $o)?->getAttribute('total');
                $g = $count(InssOutcome::Granted);
                $d = $count(InssOutcome::Denied);

                return ['label' => (string) $label, 'in_progress' => $count(InssOutcome::InProgress), 'granted' => $g, 'denied' => $d,
                    'total' => (int) $rows->sum('total'), 'rate' => FunnelMetrics::pct($g, $g + $d)];
            })
            ->sortByDesc('total')->values();

        $lastSync = IntegrationState::get(SyncC2Processes::LAST_SYNC);

        return view('livewire.administrative.dashboard', [
            'enabled' => C2Client::enabled(),
            'lastSync' => $lastSync ? CarbonImmutable::parse((string) $lastSync)->setTimezone(config('app.timezone')) : null,
            'inProgress' => $base()->where('outcome', InssOutcome::InProgress->value)->count(),
            'requirements' => $base()->openRequirement()->count(),
            'requirementsSoon' => $base()->openRequirement()->where('requirement_deadline', '<=', $in7)->count(),
            'appointments' => $base()->whereBetween('appointment_date', [$today, $in7])->count(),
            'appeals' => $base()->appealOpen()->count(),
            'monitoring' => $base()->monitoringProblem()->count(),
            'granted' => $granted,
            'denied' => $denied,
            'rate' => FunnelMetrics::pct($granted, $granted + $denied),
            'deadlines' => (new InssDeadlines)->list(14)->take(10),
            'byBenefit' => $byBenefit,
        ]);
    }
}
