<?php

namespace App\Livewire;

use App\Metrics\DashboardMetrics;
use App\Support\Period;
use App\Support\Visibility;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard: resumo em gráficos dos números mais importantes do sistema, com
 * comparação ao período anterior, tendência mensal e projeção. Quem tem
 * "dashboard.view" vê a equipe (com filtro por executivo); quem tem só
 * "dashboard.view_own" vê os próprios números.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public const WINDOWS = ['6' => '6 meses', '12' => '12 meses', '24' => '24 meses'];

    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    #[Url(as: 'tendencia', except: '12')]
    public string $window = '12';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['dashboard.view', 'dashboard.view_own']), 403);
    }

    #[On('period-changed')]
    public function periodChanged(): void {}

    /** @return list<int>|null */
    private function scope(): ?array
    {
        $user = auth()->user();

        if ($user->can('dashboard.view')) {
            return ctype_digit($this->executiveFilter) ? [(int) $this->executiveFilter] : null;
        }

        $id = $user->executive()->value('id');

        return $id ? [(int) $id] : [];
    }

    public function render(): View
    {
        $user = auth()->user();
        $period = Period::current();
        $months = (int) (array_key_exists($this->window, self::WINDOWS) ? $this->window : 12);
        $metrics = new DashboardMetrics($period, $this->scope(), $months);

        $kpis = $metrics->kpis();
        $goal = $metrics->monthGoal();
        $sources = $metrics->sources();
        $trends = [];
        foreach (['contracts', 'contracted', 'received', 'visits', 'opportunities', 'prospects'] as $metric) {
            $trends[$metric] = $metrics->trend($metric);
        }

        return view('livewire.dashboard', [
            'period' => $period,
            'kpis' => $kpis,
            'goal' => $goal,
            'trends' => $trends,
            'funnel' => $metrics->funnelStages(),
            'sources' => $sources,
            'ranking' => $metrics->ranking(),
            'heatmap' => $metrics->visitHeatmap(),
            'prospects' => $metrics->prospectsWeekly(),
            'inss' => $metrics->inssMonthly(),
            'costRevenue' => $metrics->costRevenue(),
            'insights' => DashboardMetrics::insights($kpis, $goal, $sources),
            'teamView' => $user->can('dashboard.view'),
            'executives' => $user->can('dashboard.view') ? Visibility::assignableExecutives($user) : collect(),
            // Muda quando os filtros mudam: os gráficos são recriados com os dados novos.
            'chartKey' => md5($period->start.$period->end.$this->executiveFilter.$months),
        ]);
    }
}
