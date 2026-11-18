<?php

namespace App\Metrics;

use App\Enums\ContractStatus;
use App\Enums\ExecutiveStatus;
use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Enums\InssOutcome;
use App\Enums\VisitStatus;
use App\Models\C2Process;
use App\Models\Contract;
use App\Models\ContractReceipt;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\Visit;
use App\Services\C2\C2Client;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Indicadores do Dashboard: os números mais importantes de todos os módulos,
 * a evolução mês a mês com projeção e a meta do mês.
 *
 * - KPIs: período escolhido x período anterior de mesmo tamanho.
 * - Tendência: últimos $months meses (o mês atual entra parcial).
 * - Projeção do mês atual: realizado até hoje ÷ dias decorridos × dias do mês.
 * - Próximos 3 meses: tendência linear dos meses completos (nunca negativa).
 *
 * $executiveIds: null = todos; lista = só esses executivos.
 */
final class DashboardMetrics
{
    public const FORECAST_MONTHS = 3;

    private CarbonImmutable $today;

    private CarbonImmutable $windowStart;

    /** @var array<string, array<string, float>>|null */
    private ?array $monthly = null;

    /** @param  list<int>|null  $executiveIds */
    public function __construct(private Period $period, private ?array $executiveIds = null, private int $months = 12)
    {
        $this->today = CarbonImmutable::today();
        $this->months = max(3, min(24, $months));
        $this->windowStart = $this->today->startOfMonth()->subMonthsNoOverflow($this->months - 1);
    }

    // ---------- KPIs ----------

    /**
     * @return list<array{key: string, label: string, value: float|null, previous: float|null, format: string, delta: float|null,
     *     invert: bool, hint: string, spark: list<float>, icon: string}>
     */
    public function kpis(): array
    {
        $now = $this->snapshot($this->period);
        $before = $this->snapshot($this->period->previous());
        $m = $this->monthly();

        $ticket = fn (array $s) => $s['contracts'] > 0 ? $s['contracted'] / $s['contracts'] : null;
        $costPer = fn (array $s) => $s['contracts'] > 0 ? $s['costs'] / $s['contracts'] : null;
        $sparkTicket = array_map(fn ($v, $n) => $n > 0 ? round($v / $n, 2) : 0.0, $m['contracted'], $m['contracts']);
        $sparkConversion = array_map(fn ($c, $o) => $o > 0 ? round($c / $o * 100, 1) : 0.0, $m['contracts'], $m['opportunities']);

        $items = [
            ['contracts', 'Contratos assinados', $now['contracts'], $before['contracts'], 'int', false, 'no período', $m['contracts'], 'document-check'],
            ['contracted', 'Receita contratada', $now['contracted'], $before['contracted'], 'money', false, 'valor dos contratos assinados', $m['contracted'], 'banknotes'],
            ['received', 'Receita recebida', $now['received'], $before['received'], 'money', false, 'recebimentos lançados', $m['received'], 'arrow-down-tray'],
            ['conversion', 'Taxa de conversão', $now['conversion'], $before['conversion'], 'pct', false, 'oportunidades → contratos', $sparkConversion, 'receipt-percent'],
            ['ticket', 'Ticket médio', $ticket($now), $ticket($before), 'money', false, 'por contrato', $sparkTicket, 'scale'],
            ['visits', 'Visitas realizadas', $now['visits'], $before['visits'], 'int', false, 'concluídas no período', $m['visits'], 'map-pin'],
            ['cost_per_contract', 'Custo por contrato', $costPer($now), $costPer($before), 'money', true, 'custo comercial ÷ contratos', $m['costs'], 'circle-stack'],
            ['days_to_sign', 'Dias até assinar', $now['days_to_sign'], $before['days_to_sign'], 'days', true, 'da oportunidade ao contrato', [], 'clock'],
            ['prospects', 'Interessados novos', $now['prospects'], $before['prospects'], 'int', false, 'chegaram pelo WhatsApp', $m['prospects'], 'chat-bubble-left-right'],
        ];

        if (C2Client::enabled()) {
            $items[] = ['inss_rate', 'Deferimento no INSS', $now['inss_rate'], $before['inss_rate'], 'pct', false, 'deferidos ÷ decididos', $this->inssRateSpark(), 'check-badge'];
        }

        return array_values(array_map(fn (array $i) => [
            'key' => $i[0], 'label' => $i[1], 'value' => $i[2] === null ? null : (float) $i[2], 'previous' => $i[3] === null ? null : (float) $i[3],
            'format' => $i[4], 'invert' => $i[5], 'hint' => $i[6], 'spark' => $i[7], 'icon' => $i[8],
            'delta' => $i[2] === null || $i[3] === null ? null : FunnelMetrics::delta((float) $i[2], (float) $i[3]),
        ], $items));
    }

    /** @return array{contracts: int, contracted: float, received: float, visits: int, costs: float, conversion: float|null, days_to_sign: float|null, prospects: int, inss_rate: float|null} */
    private function snapshot(Period $period): array
    {
        $funnel = new FunnelMetrics($period, $this->executiveIds);
        $dates = [$period->start->toDateString(), $period->end->toDateString()];
        $inss = C2Client::enabled() ? (new InssMetrics($period, $this->executiveIds))->totals()['rate'] : null;

        return [
            'contracts' => $funnel->contractsSigned(),
            'contracted' => (float) $funnel->contractedRevenue(),
            'received' => (float) $this->receipts()->whereBetween('contract_receipts.received_at', $dates)->sum('contract_receipts.amount'),
            'visits' => $this->visits()->whereBetween('scheduled_at', $period->range())->count(),
            'costs' => (float) $this->costs()->whereBetween('spent_on', $dates)->sum('amount'),
            'conversion' => $funnel->funnel()['overall'],
            'days_to_sign' => $funnel->averageDaysToSign(),
            'prospects' => $this->prospects()->whereBetween('entered_at', $dates)->count(),
            'inss_rate' => $inss,
        ];
    }

    // ---------- Evolução mensal e projeção ----------

    /** @return list<string> rótulos dos meses da janela, ex.: "Nov/26" */
    public function monthLabels(int $extra = 0): array
    {
        $labels = [];
        for ($i = 0; $i < $this->months + $extra; $i++) {
            $labels[] = ucfirst($this->windowStart->addMonthsNoOverflow($i)->translatedFormat('M/y'));
        }

        return $labels;
    }

    /**
     * Série de cada indicador por mês (o último é o mês atual, parcial).
     *
     * @return array<string, list<float>>
     */
    public function monthly(): array
    {
        if ($this->monthly !== null) {
            return array_map(fn ($s) => array_values($s), $this->monthly);
        }

        $keys = [];
        for ($i = 0; $i < $this->months; $i++) {
            $keys[$this->windowStart->addMonthsNoOverflow($i)->format('Y-m')] = 0.0;
        }
        $start = $this->windowStart->toDateString();
        $fill = function (Collection $rows) use ($keys): array {
            $out = $keys;
            foreach ($rows as $k => $v) {
                if (array_key_exists((string) $k, $out)) {
                    $out[(string) $k] = round((float) $v, 2);
                }
            }

            return $out;
        };

        $signed = $this->contracts()->where('signed_at', '>=', $start)
            ->selectRaw("date_format(signed_at, '%Y-%m') as ym, count(*) as n, sum(contract_value) as v")->groupBy('ym')->toBase()->get();

        $this->monthly = [
            'contracts' => $fill($signed->pluck('n', 'ym')),
            'contracted' => $fill($signed->pluck('v', 'ym')),
            'received' => $fill($this->receipts()->where('contract_receipts.received_at', '>=', $start)
                ->selectRaw("date_format(contract_receipts.received_at, '%Y-%m') as ym, sum(contract_receipts.amount) as v")->groupBy('ym')->toBase()->pluck('v', 'ym')),
            'visits' => $fill($this->visits()->where('scheduled_at', '>=', $this->windowStart)
                ->selectRaw("date_format(scheduled_at, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->toBase()->pluck('n', 'ym')),
            'opportunities' => $fill($this->opportunities()->where('opportunities.created_at', '>=', $this->windowStart)
                ->selectRaw("date_format(opportunities.created_at, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->toBase()->pluck('n', 'ym')),
            'costs' => $fill($this->costs()->where('spent_on', '>=', $start)
                ->selectRaw("date_format(spent_on, '%Y-%m') as ym, sum(amount) as v")->groupBy('ym')->toBase()->pluck('v', 'ym')),
            'prospects' => $fill($this->prospects()->where('entered_at', '>=', $start)
                ->selectRaw("date_format(entered_at, '%Y-%m') as ym, count(*) as n")->groupBy('ym')->toBase()->pluck('n', 'ym')),
        ];

        return array_map(fn ($s) => array_values($s), $this->monthly);
    }

    /**
     * Série com projeção, pronta para o gráfico de tendência.
     *
     * @return array{labels: list<string>, actual: list<float|null>, projection: list<float|null>, projected_month: float, money: bool, title: string}
     */
    public function trend(string $metric): array
    {
        $values = $this->monthly()[$metric] ?? [];
        $n = count($values);
        $projectedMonth = self::paceProjection((float) ($values[$n - 1] ?? 0), $this->today);
        $future = self::linearForecast(array_slice($values, max(0, $n - 7), min(6, $n - 1)), self::FORECAST_MONTHS);

        $actual = array_merge($values, array_fill(0, self::FORECAST_MONTHS, null));
        $projection = array_fill(0, $n + self::FORECAST_MONTHS, null);
        if ($n >= 2) {
            $projection[$n - 2] = $values[$n - 2]; // liga a linha ao último mês fechado
        }
        $projection[$n - 1] = round($projectedMonth, 2);
        foreach ($future as $i => $v) {
            $projection[$n + $i] = $v;
        }

        return [
            'labels' => $this->monthLabels(self::FORECAST_MONTHS),
            'actual' => $actual,
            'projection' => array_values($projection),
            'projected_month' => round($projectedMonth, 2),
            'money' => in_array($metric, ['contracted', 'received', 'costs'], true),
            'title' => match ($metric) {
                'contracts' => 'Contratos', 'contracted' => 'Receita contratada', 'received' => 'Receita recebida',
                'visits' => 'Visitas', 'opportunities' => 'Oportunidades', 'prospects' => 'Interessados', default => $metric,
            },
        ];
    }

    /** Valor do mês inteiro mantido o ritmo até hoje. */
    public static function paceProjection(float $monthToDate, CarbonImmutable $today): float
    {
        return $monthToDate / max(1, $today->day) * $today->daysInMonth;
    }

    /**
     * Tendência linear (mínimos quadrados) dos pontos informados, projetada
     * para os próximos $ahead pontos. Nunca negativa.
     *
     * @param  list<float>  $points
     * @return list<float>
     */
    public static function linearForecast(array $points, int $ahead): array
    {
        $n = count($points);
        if ($n === 0) {
            return array_fill(0, $ahead, 0.0);
        }
        if ($n < 3) {
            return array_fill(0, $ahead, round(array_sum($points) / $n, 2));
        }

        $xMean = ($n - 1) / 2;
        $yMean = array_sum($points) / $n;
        $num = $den = 0.0;
        foreach ($points as $x => $y) {
            $num += ($x - $xMean) * ($y - $yMean);
            $den += ($x - $xMean) ** 2;
        }
        $slope = $den > 0 ? $num / $den : 0.0;
        $intercept = $yMean - $slope * $xMean;

        $out = [];
        for ($i = 1; $i <= $ahead; $i++) {
            $out[] = round(max(0.0, $intercept + $slope * ($n - 1 + $i)), 2);
        }

        return $out;
    }

    // ---------- Meta do mês ----------

    /**
     * Meta da equipe (soma das metas dos executivos) no mês atual, realizado e projeção.
     *
     * @return array{month: CarbonImmutable, elapsed_pct: float, items: list<array{label: string, done: int, target: int|null, projected: float, pct: float|null, projected_pct: float|null}>}
     */
    public function monthGoal(): array
    {
        $month = $this->today->startOfMonth();
        $executives = Executive::query()->where('status', ExecutiveStatus::Active->value)
            ->when($this->executiveIds !== null, fn ($q) => $q->whereKey($this->executiveIds ?: [0]))->get();

        $targets = ['visits' => null, 'contracts' => null];
        foreach ($executives as $executive) {
            $t = $executive->targetsFor($month);
            foreach (['visits', 'contracts'] as $k) {
                if ($t[$k] !== null) {
                    $targets[$k] = ($targets[$k] ?? 0) + (int) $t[$k];
                }
            }
        }

        $done = [
            'contracts' => $this->contracts()->where('signed_at', '>=', $month->toDateString())->count(),
            'visits' => $this->visits()->where('scheduled_at', '>=', $month)->count(),
        ];

        $items = [];
        foreach (['contracts' => 'Contratos', 'visits' => 'Visitas'] as $k => $label) {
            $projected = self::paceProjection($done[$k], $this->today);
            $items[] = [
                'label' => $label,
                'done' => $done[$k],
                'target' => $targets[$k],
                'projected' => round($projected, 1),
                'pct' => $targets[$k] ? round($done[$k] / $targets[$k] * 100, 1) : null,
                'projected_pct' => $targets[$k] ? round($projected / $targets[$k] * 100, 1) : null,
            ];
        }

        return ['month' => $month, 'elapsed_pct' => round($this->today->day / $this->today->daysInMonth * 100, 1), 'items' => $items];
    }

    // ---------- Distribuições ----------

    /** @return list<array{label: string, count: int, conversion: float|null}> */
    public function funnelStages(): array
    {
        return array_map(fn (array $s) => [
            'label' => $s['stage']->label(), 'count' => $s['count'], 'conversion' => $s['conversion'],
        ], (new FunnelMetrics($this->period, $this->executiveIds))->funnel()['stages']);
    }

    /** @return array{labels: list<string>, values: list<float>, counts: list<int>} */
    public function sources(): array
    {
        $rows = (new FinanceMetrics($this->period, $this->executiveIds))->revenueBySource();

        return [
            'labels' => array_column($rows, 'source'),
            'values' => array_map(fn ($r) => (float) $r['total'], $rows),
            'counts' => array_column($rows, 'contracts'),
        ];
    }

    /** @return array{labels: list<string>, contracts: list<int>, revenue: list<float>, visits: list<int>} */
    public function ranking(int $limit = 8): array
    {
        $rows = array_slice((new ExecutivePerformance($this->period))->ranking($this->executiveIds), 0, $limit);
        $revenue = $this->contracts()
            ->whereBetween('signed_at', [$this->period->start->toDateString(), $this->period->end->toDateString()])
            ->selectRaw('executive_id, sum(contract_value) as v')->groupBy('executive_id')->pluck('v', 'executive_id');

        return [
            'labels' => array_map(fn ($r) => self::shortName($r['executive']->user->name), $rows),
            'contracts' => array_map(fn ($r) => $r['contracts'], $rows),
            'revenue' => array_map(fn ($r) => round((float) ($revenue[$r['executive']->id] ?? 0), 2), $rows),
            'visits' => array_map(fn ($r) => $r['visits'], $rows),
        ];
    }

    /** "Darlan da Silva" vira "Darlan S.", para caber no eixo do gráfico. */
    public static function shortName(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name)), fn ($p) => mb_strlen($p) > 2));

        return count($parts) > 1 ? $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : trim($name);
    }

    /**
     * Visitas realizadas por dia da semana e faixa de horário, no período.
     *
     * @return list<array{name: string, data: list<array{x: string, y: int}>}>
     */
    public function visitHeatmap(): array
    {
        $hours = range(7, 19);
        $grid = [];
        foreach ($this->visits()->whereBetween('scheduled_at', $this->period->range())->pluck('scheduled_at') as $at) {
            $at = CarbonImmutable::parse($at);
            $hour = max(7, min(19, $at->hour));
            $grid[$at->dayOfWeekIso][$hour] = ($grid[$at->dayOfWeekIso][$hour] ?? 0) + 1;
        }

        $days = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
        $series = [];
        foreach (array_reverse($days, true) as $d => $name) {
            $series[] = ['name' => $name, 'data' => array_map(fn ($h) => ['x' => "{$h}h", 'y' => (int) ($grid[$d][$h] ?? 0)], $hours)];
        }

        return $series;
    }

    /**
     * Interessados por semana: chegaram, qualificados e descartados (últimas 12 semanas).
     *
     * @return array{labels: list<string>, new: list<int>, qualified: list<int>, discarded: list<int>}
     */
    public function prospectsWeekly(int $weeks = 12): array
    {
        $start = $this->today->startOfWeek()->subWeeks($weeks - 1);
        $out = ['labels' => [], 'new' => array_fill(0, $weeks, 0), 'qualified' => array_fill(0, $weeks, 0), 'discarded' => array_fill(0, $weeks, 0)];
        for ($i = 0; $i < $weeks; $i++) {
            $out['labels'][] = $start->addWeeks($i)->format('d/m');
        }
        $bucket = fn ($date) => $date === null ? null : (int) floor($start->diffInDays(CarbonImmutable::parse($date)->startOfDay(), false) / 7);

        $rows = $this->prospects()->where(fn ($q) => $q->where('entered_at', '>=', $start->toDateString())
            ->orWhere('qualified_at', '>=', $start)->orWhere('discarded_at', '>=', $start))
            ->get(['entered_at', 'qualified_at', 'discarded_at']);

        foreach ($rows as $p) {
            foreach (['new' => $p->entered_at, 'qualified' => $p->qualified_at, 'discarded' => $p->discarded_at] as $k => $date) {
                $b = $bucket($date);
                if ($b !== null && $b >= 0 && $b < $weeks) {
                    $out[$k][$b]++;
                }
            }
        }

        return ['labels' => $out['labels'], 'new' => array_values($out['new']), 'qualified' => array_values($out['qualified']), 'discarded' => array_values($out['discarded'])];
    }

    /**
     * Decisões do INSS por mês (protocolos do escritório) e taxa de deferimento.
     *
     * @return array{labels: list<string>, granted: list<int>, denied: list<int>, rate: list<float|null>}|null
     */
    public function inssMonthly(): ?array
    {
        if (! C2Client::enabled()) {
            return null;
        }

        $rows = $this->inss()->where('concluded_at', '>=', $this->windowStart->toDateString())
            ->whereIn('outcome', [InssOutcome::Granted->value, InssOutcome::Denied->value])
            ->selectRaw("date_format(concluded_at, '%Y-%m') as ym, outcome, count(*) as n")->groupBy('ym', 'outcome')->toBase()->get();

        $out = ['labels' => $this->monthLabels(), 'granted' => [], 'denied' => [], 'rate' => []];
        for ($i = 0; $i < $this->months; $i++) {
            $key = $this->windowStart->addMonthsNoOverflow($i)->format('Y-m');
            $g = (int) ($rows->first(fn ($r) => $r->ym === $key && $r->outcome === InssOutcome::Granted->value)->n ?? 0);
            $d = (int) ($rows->first(fn ($r) => $r->ym === $key && $r->outcome === InssOutcome::Denied->value)->n ?? 0);
            $out['granted'][] = $g;
            $out['denied'][] = $d;
            $out['rate'][] = FunnelMetrics::pct($g, $g + $d);
        }

        return $out;
    }

    /** @return list<float> */
    private function inssRateSpark(): array
    {
        return array_map(fn ($r) => (float) ($r ?? 0), $this->inssMonthly()['rate'] ?? []);
    }

    /** @return array{labels: list<string>, costs: list<float>, revenue: list<float>, received: list<float>} */
    public function costRevenue(): array
    {
        $m = $this->monthly();

        return ['labels' => $this->monthLabels(), 'costs' => $m['costs'], 'revenue' => $m['contracted'], 'received' => $m['received']];
    }

    // ---------- Leituras automáticas ----------

    /**
     * Frases curtas com o que mais chama a atenção nos números.
     *
     * @param  list<array<string, mixed>>  $kpis
     * @param  array<string, mixed>  $goal
     * @param  array<string, mixed>  $sources
     * @return list<array{tone: string, icon: string, text: string}>
     */
    public static function insights(array $kpis, array $goal, array $sources): array
    {
        $out = [];
        $byKey = collect($kpis)->keyBy('key');
        $pct = fn (float $v) => number_format(abs($v), 1, ',', '.').'%';

        foreach (['contracted' => 'A receita contratada', 'contracts' => 'O número de contratos', 'received' => 'A receita recebida'] as $key => $label) {
            $delta = $byKey[$key]['delta'] ?? null;
            if ($delta !== null && abs($delta) >= 10) {
                $out[] = $delta > 0
                    ? ['tone' => 'good', 'icon' => 'arrow-trending-up', 'text' => "{$label} subiu {$pct($delta)} em relação ao período anterior."]
                    : ['tone' => 'bad', 'icon' => 'arrow-trending-down', 'text' => "{$label} caiu {$pct($delta)} em relação ao período anterior."];
                break;
            }
        }

        foreach ($goal['items'] as $item) {
            if ($item['projected_pct'] !== null) {
                $out[] = $item['projected_pct'] >= 100
                    ? ['tone' => 'good', 'icon' => 'flag', 'text' => "No ritmo atual, a meta de {$item['label']} do mês será batida ({$pct($item['projected_pct'])} projetado)."]
                    : ['tone' => 'warn', 'icon' => 'flag', 'text' => "No ritmo atual, {$item['label']} fecham o mês em {$pct($item['projected_pct'])} da meta."];
                break;
            }
        }

        if (($sources['values'] ?? []) !== [] && array_sum($sources['values']) > 0) {
            $i = array_keys($sources['values'], max($sources['values']))[0];
            $share = $sources['values'][$i] / array_sum($sources['values']) * 100;
            $out[] = ['tone' => 'info', 'icon' => 'star', 'text' => "{$sources['labels'][$i]} é a origem que mais gera receita: {$pct($share)} do contratado no período."];
        }

        $cost = $byKey['cost_per_contract']['delta'] ?? null;
        if ($cost !== null && abs($cost) >= 10) {
            $out[] = $cost < 0
                ? ['tone' => 'good', 'icon' => 'arrow-trending-down', 'text' => "O custo por contrato caiu {$pct($cost)}."]
                : ['tone' => 'warn', 'icon' => 'arrow-trending-up', 'text' => "O custo por contrato subiu {$pct($cost)}."];
        }

        return array_slice($out, 0, 4);
    }

    // ---------- Consultas base ----------

    /** @return Builder<Contract> */
    private function contracts(): Builder
    {
        return Contract::query()->where('contracts.status', ContractStatus::Signed->value)
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('contracts.executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<ContractReceipt> */
    private function receipts(): Builder
    {
        return ContractReceipt::query()->join('contracts', 'contracts.id', '=', 'contract_receipts.contract_id')
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('contracts.executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<Visit> */
    private function visits(): Builder
    {
        return Visit::query()->where('status', VisitStatus::Done->value)
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<Opportunity> */
    private function opportunities(): Builder
    {
        return Opportunity::query()
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('opportunities.executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<Expense> */
    private function costs(): Builder
    {
        return Expense::query()->where('kind', ExpenseKind::Actual->value)->where('status', ExpenseStatus::Approved->value)
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<Prospect> */
    private function prospects(): Builder
    {
        return Prospect::query()
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('executive_id', $this->executiveIds ?: [0]));
    }

    /** @return Builder<C2Process> */
    private function inss(): Builder
    {
        return C2Process::query()->inScope()
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('lead_id', Lead::query()->whereIn('executive_id', $this->executiveIds ?: [0])->select('id')));
    }
}
