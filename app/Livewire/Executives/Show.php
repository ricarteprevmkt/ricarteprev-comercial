<?php

namespace App\Livewire\Executives;

use App\Enums\VisitStatus;
use App\Metrics\ExecutivePerformance;
use App\Metrics\FunnelMetrics;
use App\Models\Executive;
use App\Models\Visit;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Desempenho individual do executivo. Acesso: quem vê os executivos, ou o
 * próprio executivo (com "dashboard.view_own").
 */
#[Title('Desempenho do executivo')]
class Show extends Component
{
    public Executive $executive;

    /** Métrica do comparativo entre executivos. */
    #[Url(as: 'comparar', except: 'visits')]
    public string $compareBy = 'visits';

    public function mount(Executive $executive): void
    {
        $user = auth()->user();
        $own = $executive->user_id === $user->id && $user->can('dashboard.view_own');
        abort_unless($own || $user->can('executives.view'), 403);

        $this->executive = $executive;
    }

    #[On('period-changed')]
    public function periodChanged(): void
    {
        $m = new ExecutivePerformance(Period::current());
        $this->dispatch('exec-daily', data: $m->daily($this->executive));
        $this->dispatch('exec-targets', data: $m->monthlyVsTargets($this->executive));
        $this->dispatch('exec-reasons', data: $this->reasonsChart($m->notDoneReasons($this->executive)));
        $this->dispatch('exec-ranking', data: $this->rankingChart($m));
    }

    public function updatedCompareBy(): void
    {
        $this->dispatch('exec-ranking', data: $this->rankingChart(new ExecutivePerformance(Period::current())));
    }

    /**
     * @param  list<array{reason: string, count: int, share: float|null}>  $reasons
     * @return array{labels: list<string>, values: list<int>, total_label: string, money: false}
     */
    private function reasonsChart(array $reasons): array
    {
        return [
            'labels' => array_column($reasons, 'reason'),
            'values' => array_column($reasons, 'count'),
            'total_label' => 'Visitas',
            'money' => false,
        ];
    }

    /** @return array{labels: list<string>, values: list<float|int>, name: string} */
    private function rankingChart(ExecutivePerformance $m): array
    {
        $key = in_array($this->compareBy, ['visits', 'contracts', 'conversion', 'opportunities'], true) ? $this->compareBy : 'visits';
        $rows = $m->ranking(auth()->user()->can('executives.view') ? null : [$this->executive->id]);
        usort($rows, fn ($a, $b) => ($b[$key] ?? 0) <=> ($a[$key] ?? 0));

        return [
            'labels' => array_map(fn ($r) => $r['executive']->user->name, $rows),
            'values' => array_map(fn ($r) => $r[$key] ?? 0, $rows),
            'name' => ['visits' => 'Visitas', 'contracts' => 'Contratos', 'conversion' => 'Conversão (%)', 'opportunities' => 'Oportunidades'][$key],
        ];
    }

    public function render(): View
    {
        $period = Period::current();
        $m = new ExecutivePerformance($period);
        $this->executive->load(['user', 'city', 'region']);
        $summary = $m->summary($this->executive);
        $previous = (new ExecutivePerformance($period->previous()))->summary($this->executive);
        $reasons = $m->notDoneReasons($this->executive);

        $today = CarbonImmutable::today();

        return view('livewire.executives.show', [
            'period' => $period,
            's' => $summary,
            'p' => $previous,
            'delta' => fn ($a, $b) => $a === null || $b === null ? null : FunnelMetrics::delta((float) $a, (float) $b),
            'goal' => $m->goalProgress($this->executive, $period->end->gt($today) ? $today : $period->end),
            'daily' => $m->daily($this->executive),
            'targets' => $m->monthlyVsTargets($this->executive),
            'reasons' => $reasons,
            'reasonsChart' => $this->reasonsChart($reasons),
            'rankingChart' => $this->rankingChart($m),
            'canCompare' => auth()->user()->can('executives.view'),
            'todayVisits' => Visit::query()->where('executive_id', $this->executive->id)->onDate($today)->with(['lead', 'city'])->orderBy('scheduled_at')->get(),
            'openOpportunities' => $m->openOpportunities($this->executive),
            'doneStatus' => VisitStatus::Done,
        ])->title($this->executive->user->name);
    }
}
