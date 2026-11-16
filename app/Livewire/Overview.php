<?php

namespace App\Livewire;

use App\Enums\OpportunityStage;
use App\Enums\VisitStatus;
use App\Metrics\AgendaMetrics;
use App\Metrics\Alerts;
use App\Metrics\ExecutivePerformance;
use App\Metrics\FinanceMetrics;
use App\Metrics\FunnelMetrics;
use App\Models\City;
use App\Models\Opportunity;
use App\Models\Visit;
use App\Support\Period;
use App\Support\Visibility;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Visão geral: painel executivo central. Quem tem "dashboard.view" vê a
 * equipe (com filtro por executivo); quem tem só "dashboard.view_own" vê os
 * próprios números.
 */
#[Title('Visão geral')]
class Overview extends Component
{
    #[Url(as: 'executivo', except: '')]
    public string $executiveFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['dashboard.view', 'dashboard.view_own']), 403);
    }

    /** O funil é desenhado no servidor: basta renderizar de novo com o novo período. */
    #[On('period-changed')]
    public function periodChanged(): void {}

    /** @return list<int>|null */
    private function scope(): ?array
    {
        $user = auth()->user();

        if ($user->can('dashboard.view')) {
            return $this->executiveFilter !== '' ? [(int) $this->executiveFilter] : null;
        }

        $id = $user->executive()->value('id');

        return $id ? [(int) $id] : [];
    }

    /**
     * @param  array<string, mixed>  $evolution
     * @return list<array{label: string, count: int, note: string}>
     */
    private function funnelChart(array $evolution): array
    {
        $values = [
            'Visitas' => (int) $evolution['visits'],
            'Oportunidades' => (int) $evolution['opportunities'],
            'Atendimentos agendados' => (int) $evolution['scheduled'],
            'Propostas apresentadas' => (int) $evolution['proposals'],
            'Contratos' => (int) $evolution['contracts'],
        ];

        $stages = [];
        $previous = null;
        foreach ($values as $label => $count) {
            $note = match (true) {
                $previous === null => '',
                $previous === 0 => '—',
                default => number_format($count / $previous * 100, 1, ',', '.').'%',
            };
            $stages[] = ['label' => $label, 'count' => $count, 'note' => $note];
            $previous = $count;
        }

        return $stages;
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $period = Period::current();
        $scope = $this->scope();

        $agenda = (new AgendaMetrics($period->start, $period->end, $scope))->summary();
        $prevAgenda = (new AgendaMetrics($period->previous()->start, $period->previous()->end, $scope))->summary();
        $funnelM = new FunnelMetrics($period, $scope);
        $funnel = $funnelM->funnel();
        $prevFunnel = $funnelM->previous()->funnel();
        $finance = (new FinanceMetrics($period, $scope))->summary();
        $prevFinance = (new FinanceMetrics($period->previous(), $scope))->summary();

        $fromVisits = Opportunity::query()->whereNotNull('visit_id')->whereBetween('created_at', $period->range())
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))->count();

        $stages = $funnel['stages'];

        return [
            'period' => $period,
            'agenda' => $agenda,
            'prevAgenda' => $prevAgenda,
            'funnel' => $funnel,
            'prevFunnel' => $prevFunnel,
            'finance' => $finance,
            'prevFinance' => $prevFinance,
            'evolution' => [
                'visits' => $agenda['done'],
                'opportunities' => $stages[0]['count'],
                'scheduled' => $stages[2]['count'],
                'proposals' => $stages[3]['count'],
                'contracts' => $stages[4]['count'],
                'visit_to_opportunity' => FunnelMetrics::pct($fromVisits, $agenda['done']),
                'proposal_to_contract' => $stages[4]['conversion'],
                'overall' => $funnel['overall'],
            ],
        ];
    }

    public function render(): View
    {
        $user = auth()->user();
        $data = $this->build();
        $scope = $this->scope();
        $period = $data['period'];
        $today = CarbonImmutable::today();

        $performance = new ExecutivePerformance($period);
        $previousRanking = collect((new ExecutivePerformance($period->previous()))->ranking($scope))->keyBy(fn ($r) => $r['executive']->id);
        $ranking = array_map(function ($row) use ($previousRanking) {
            $prev = $previousRanking[$row['executive']->id] ?? null;
            $row['delta'] = $prev ? FunnelMetrics::delta($row['contracts'], $prev['contracts']) : null;

            return $row;
        }, $performance->ranking($scope));

        $todayAgenda = AgendaMetrics::day($today, $scope)->summary();
        $upcoming = Visit::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->whereIn('status', [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value])
            ->whereBetween('scheduled_at', [now(), now()->addHours(2)])
            ->with(['lead', 'executive.user'])->orderBy('scheduled_at')->limit(5)->get();

        $inProgress = Opportunity::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->open()->with(['lead', 'executive.user'])
            ->latest('last_activity_at')->limit(6)->get();

        $byCity = $this->regional($period, $scope);
        $own = $user->executive()->first();

        return view('livewire.overview', $data + [
            'teamView' => $user->can('dashboard.view'),
            'ranking' => array_slice($ranking, 0, 8),
            'todayAgenda' => $todayAgenda,
            'overdueCount' => Opportunity::query()->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))->overdue()->count(),
            'upcoming' => $upcoming,
            'inProgress' => $inProgress,
            'byCity' => $byCity,
            'alerts' => array_values(array_filter(Alerts::for($user), fn ($a) => $a['count'] > 0)),
            'executives' => $user->can('dashboard.view') ? Visibility::assignableExecutives($user) : collect(),
            'funnelChart' => $this->funnelChart($data['evolution']),
            'ownExecutive' => $own,
            'ownGoal' => $own ? $performance->goalProgress($own, $today) : null,
            'stageLabels' => collect(OpportunityStage::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }

    /**
     * Visitas concluídas e oportunidades abertas por cidade no período.
     *
     * @param  list<int>|null  $scope
     * @return list<array{city: string, visits: int, opportunities: int}>
     */
    private function regional(Period $period, ?array $scope): array
    {
        $visits = Visit::query()->where('status', VisitStatus::Done->value)->whereBetween('scheduled_at', $period->range())
            ->when($scope !== null, fn ($q) => $q->whereIn('executive_id', $scope ?: [0]))
            ->whereNotNull('city_id')->selectRaw('city_id, count(*) as n')->groupBy('city_id')->pluck('n', 'city_id');
        $opps = Opportunity::query()->whereBetween('opportunities.created_at', $period->range())
            ->when($scope !== null, fn ($q) => $q->whereIn('opportunities.executive_id', $scope ?: [0]))
            ->join('leads', 'leads.id', '=', 'opportunities.lead_id')->whereNotNull('leads.city_id')
            ->selectRaw('leads.city_id, count(*) as n')->groupBy('leads.city_id')->pluck('n', 'city_id');

        $ids = collect([...$visits->keys(), ...$opps->keys()])->unique();
        $names = City::query()->whereKey($ids)->pluck('name', 'id');

        $rows = [];
        foreach ($ids as $id) {
            $rows[] = ['city' => (string) ($names[$id] ?? '—'), 'visits' => (int) ($visits[$id] ?? 0), 'opportunities' => (int) ($opps[$id] ?? 0)];
        }
        usort($rows, fn ($a, $b) => [$b['visits'], $b['opportunities']] <=> [$a['visits'], $a['opportunities']]);

        return array_slice($rows, 0, 6);
    }
}
