<?php

namespace App\Metrics;

use App\Enums\ContractStatus;
use App\Enums\ExecutiveStatus;
use App\Enums\VisitStatus;
use App\Models\Catalogs\VisitReason;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Opportunity;
use App\Models\Visit;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Desempenho individual de executivos: visitas, oportunidades, contratos,
 * conversão, km, custos e metas. Reaproveita FunnelMetrics, AgendaMetrics e
 * FinanceMetrics para que os números batam com os outros painéis.
 */
final class ExecutivePerformance
{
    public function __construct(private Period $period) {}

    /**
     * @return array{visits_done: int, visits_planned: int, opportunities: int, contracts: int, conversion: float|null,
     *     km_reported: float, km_estimated: float, cost: string, cost_per_visit: string|null, cost_per_contract: string|null, contracted: string}
     */
    public function summary(Executive $executive): array
    {
        $ids = [$executive->id];
        $agenda = (new AgendaMetrics($this->period->start, $this->period->end, $ids))->summary();
        $funnel = (new FunnelMetrics($this->period, $ids))->funnel();
        $finance = (new FinanceMetrics($this->period, $ids))->summary();

        return [
            'visits_done' => $agenda['done'],
            'visits_planned' => $agenda['planned'],
            'opportunities' => $funnel['base'],
            'contracts' => $finance['contracts_signed'],
            'conversion' => $funnel['overall'],
            'km_reported' => $agenda['km_reported'],
            'km_estimated' => $agenda['km_estimated'],
            'cost' => $finance['operational'],
            'cost_per_visit' => $finance['cost_per_visit'],
            'cost_per_contract' => $finance['cost_per_contract'],
            'contracted' => $finance['contracted'],
        ];
    }

    /**
     * Visitas concluídas e contratos assinados por dia no período (máx. 92 dias; acima disso, por mês).
     *
     * @return array{labels: list<string>, visits: list<int>, contracts: list<int>}
     */
    public function daily(Executive $executive): array
    {
        $byMonth = $this->period->days() > 92;
        $format = $byMonth ? '%Y-%m' : '%Y-%m-%d';

        $visits = Visit::query()->where('executive_id', $executive->id)->where('status', VisitStatus::Done->value)
            ->whereBetween('scheduled_at', $this->period->range())
            ->selectRaw("date_format(scheduled_at, '{$format}') as d, count(*) as n")->groupBy('d')->pluck('n', 'd');
        $contracts = Contract::query()->where('executive_id', $executive->id)->where('status', ContractStatus::Signed->value)
            ->whereBetween('signed_at', [$this->period->start->toDateString(), $this->period->end->toDateString()])
            ->selectRaw("date_format(signed_at, '{$format}') as d, count(*) as n")->groupBy('d')->pluck('n', 'd');

        $out = ['labels' => [], 'visits' => [], 'contracts' => []];
        $cursor = $byMonth ? $this->period->start->startOfMonth() : $this->period->start;

        while ($cursor->lte($this->period->end)) {
            $key = $byMonth ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $out['labels'][] = $byMonth ? ucfirst($cursor->translatedFormat('M/y')) : $cursor->format('d/m');
            $out['visits'][] = (int) ($visits[$key] ?? 0);
            $out['contracts'][] = (int) ($contracts[$key] ?? 0);
            $cursor = $byMonth ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return $out;
    }

    /**
     * Últimos meses: realizado x meta (meta do mês ou a padrão).
     *
     * @return array{labels: list<string>, visits: list<int>, visits_target: list<int|null>, contracts: list<int>, contracts_target: list<int|null>}
     */
    public function monthlyVsTargets(Executive $executive, int $months = 6): array
    {
        $out = ['labels' => [], 'visits' => [], 'visits_target' => [], 'contracts' => [], 'contracts_target' => []];
        $end = $this->period->end->startOfMonth();

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $end->subMonthsNoOverflow($i);
            $range = [$month->startOfMonth(), $month->endOfMonth()];
            $targets = $executive->targetsFor($month);

            $out['labels'][] = ucfirst($month->translatedFormat('M/y'));
            $out['visits'][] = Visit::query()->where('executive_id', $executive->id)->where('status', VisitStatus::Done->value)->whereBetween('scheduled_at', $range)->count();
            $out['contracts'][] = Contract::query()->where('executive_id', $executive->id)->where('status', ContractStatus::Signed->value)
                ->whereBetween('signed_at', [$range[0]->toDateString(), $range[1]->toDateString()])->count();
            $out['visits_target'][] = $targets['visits'];
            $out['contracts_target'][] = $targets['contracts'];
        }

        return $out;
    }

    /**
     * Andamento da meta no mês de referência (o mês do fim do período).
     *
     * @return array{month: CarbonImmutable, visits: int, contracts: int, visits_target: int|null, contracts_target: int|null,
     *     visits_pct: float|null, contracts_pct: float|null, expected_pct: float, below: bool}
     */
    public function goalProgress(Executive $executive, ?CarbonImmutable $reference = null): array
    {
        $today = CarbonImmutable::today();
        $month = ($reference ?? $this->period->end)->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $until = $monthEnd->lt($today) ? $monthEnd : $today->endOfDay();
        $targets = $executive->targetsFor($month);

        $visits = Visit::query()->where('executive_id', $executive->id)->where('status', VisitStatus::Done->value)
            ->whereBetween('scheduled_at', [$month, $until])->count();
        $contracts = Contract::query()->where('executive_id', $executive->id)->where('status', ContractStatus::Signed->value)
            ->whereBetween('signed_at', [$month->toDateString(), $until->toDateString()])->count();

        // Ritmo esperado: proporção do mês já decorrida.
        $expected = $month->gt($today) ? 0.0 : min(1.0, ($until->day) / $monthEnd->day);
        $vPct = $targets['visits'] ? round($visits / $targets['visits'] * 100, 1) : null;
        $cPct = $targets['contracts'] ? round($contracts / $targets['contracts'] * 100, 1) : null;
        $below = ($vPct !== null && $vPct < $expected * 100) || ($cPct !== null && $cPct < $expected * 100);

        return [
            'month' => $month,
            'visits' => $visits,
            'contracts' => $contracts,
            'visits_target' => $targets['visits'],
            'contracts_target' => $targets['contracts'],
            'visits_pct' => $vPct,
            'contracts_pct' => $cPct,
            'expected_pct' => round($expected * 100, 1),
            'below' => $below,
        ];
    }

    /**
     * Executivos ativos abaixo do ritmo da meta no mês atual.
     *
     * @param  list<int>|null  $executiveIds
     * @return list<array{executive: Executive, progress: array<string, mixed>}>
     */
    public function belowGoal(?array $executiveIds = null): array
    {
        $executives = Executive::query()->with('user')
            ->where('status', ExecutiveStatus::Active->value)
            ->when($executiveIds !== null, fn ($q) => $q->whereKey($executiveIds ?: [0]))
            ->get();

        $out = [];
        foreach ($executives as $executive) {
            $progress = $this->goalProgress($executive, CarbonImmutable::today());
            if ($progress['below']) {
                $out[] = ['executive' => $executive, 'progress' => $progress];
            }
        }

        return $out;
    }

    /**
     * @return list<array{reason: string, count: int, share: float|null}>
     */
    public function notDoneReasons(Executive $executive): array
    {
        $rows = Visit::query()->where('executive_id', $executive->id)->where('status', VisitStatus::NotDone->value)
            ->whereBetween('scheduled_at', $this->period->range())
            ->selectRaw('not_done_reason_id, count(*) as n')->groupBy('not_done_reason_id')->pluck('n', 'not_done_reason_id');
        $total = (int) $rows->sum();
        $names = VisitReason::query()->pluck('name', 'id');

        $out = [];
        foreach ($rows as $id => $n) {
            $out[] = ['reason' => $id ? (string) ($names[$id] ?? 'Motivo removido') : 'Sem motivo', 'count' => (int) $n, 'share' => FunnelMetrics::pct((int) $n, $total)];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * Comparativo entre executivos no período.
     *
     * @param  list<int>|null  $executiveIds
     * @return list<array{executive: Executive, visits: int, contracts: int, conversion: float|null, opportunities: int}>
     */
    public function ranking(?array $executiveIds = null): array
    {
        $executives = Executive::query()->with('user')
            ->where('status', '!=', ExecutiveStatus::Terminated->value)
            ->when($executiveIds !== null, fn ($q) => $q->whereKey($executiveIds ?: [0]))
            ->get();

        $visits = Visit::query()->where('status', VisitStatus::Done->value)->whereBetween('scheduled_at', $this->period->range())
            ->selectRaw('executive_id, count(*) as n')->groupBy('executive_id')->pluck('n', 'executive_id');
        $contracts = Contract::query()->where('status', ContractStatus::Signed->value)
            ->whereBetween('signed_at', [$this->period->start->toDateString(), $this->period->end->toDateString()])
            ->selectRaw('executive_id, count(*) as n')->groupBy('executive_id')->pluck('n', 'executive_id');
        $funnel = collect((new FunnelMetrics($this->period, $executiveIds))->byExecutive())->keyBy(fn ($r) => $r['executive']->id);

        $out = [];
        foreach ($executives as $executive) {
            $out[] = [
                'executive' => $executive,
                'visits' => (int) ($visits[$executive->id] ?? 0),
                'contracts' => (int) ($contracts[$executive->id] ?? 0),
                'opportunities' => (int) ($funnel[$executive->id]['base'] ?? 0),
                'conversion' => $funnel[$executive->id]['conversion'] ?? null,
            ];
        }
        usort($out, fn ($a, $b) => [$b['contracts'], $b['visits']] <=> [$a['contracts'], $a['visits']]);

        return $out;
    }

    /**
     * Oportunidades em aberto do executivo, prazos vencidos primeiro.
     *
     * @return Collection<int, Opportunity>
     */
    public function openOpportunities(Executive $executive, int $limit = 8): Collection
    {
        return Opportunity::query()->where('executive_id', $executive->id)->open()->with('lead')
            ->orderByRaw('next_action_due is null')->orderBy('next_action_due')->latest('last_activity_at')
            ->limit($limit)->get();
    }

    public static function costLabel(?string $value): string
    {
        return $value === null ? '—' : Money::format($value);
    }
}
