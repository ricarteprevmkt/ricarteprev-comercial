<?php

namespace App\Livewire;

use App\Metrics\DashboardMetrics;
use App\Models\C2Snapshot;
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

    /**
     * Totais do escritório lidos do C2 pela tarefa agendada: o último envio e
     * a evolução diária (último envio de cada dia) dos últimos 30 dias.
     *
     * @return array{snapshot: C2Snapshot, groups: array<string, array<string, string>>, trend: array{labels: list<string>, receivable: list<float|null>, overdue: list<float|null>}}|null
     */
    private function office(bool $finance): ?array
    {
        $snapshot = C2Snapshot::latest();
        if ($snapshot === null) {
            return null;
        }

        $daily = C2Snapshot::query()->where('collected_at', '>=', now()->subDays(30))->orderBy('collected_at')->get()
            ->groupBy(fn (C2Snapshot $s) => $s->collected_at->setTimezone(config('app.timezone'))->format('d/m'))
            ->map(fn ($day) => $day->last());

        return [
            'snapshot' => $snapshot,
            'groups' => $finance ? C2Snapshot::METRICS : array_diff_key(C2Snapshot::METRICS, ['financeiro' => true]),
            'trend' => [
                'labels' => array_values(array_map('strval', $daily->keys()->all())),
                'receivable' => $finance ? array_values($daily->map(fn (?C2Snapshot $s) => $s?->value('receivable_open'))->all()) : [],
                'overdue' => array_values($daily->map(fn (?C2Snapshot $s) => $s?->value('deadlines_overdue'))->all()),
            ],
        ];
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
            'office' => $user->can('dashboard.view') ? $this->office($user->can('finance.view')) : null,
            'teamView' => $user->can('dashboard.view'),
            'executives' => $user->can('dashboard.view') ? Visibility::assignableExecutives($user) : collect(),
            // Muda quando os filtros mudam: os gráficos são recriados com os dados novos.
            'chartKey' => md5($period->start.$period->end.$this->executiveFilter.$months),
        ]);
    }
}
