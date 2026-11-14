<?php

namespace App\Metrics;

use App\Enums\CommissionStatus;
use App\Enums\ContractStatus;
use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Enums\VisitStatus;
use App\Models\Catalogs\ExpenseCategory;
use App\Models\Catalogs\LeadSource;
use App\Models\Commission;
use App\Models\Contract;
use App\Models\ContractReceipt;
use App\Models\Executive;
use App\Models\Expense;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\Visit;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Custos e resultados da operação comercial (fórmulas em docs/metricas.md).
 *
 * - Custos: só despesas EFETIVAS e APROVADAS. Pendentes aparecem à parte.
 * - Receita contratada: valor dos contratos assinados no período.
 * - Receita recebida: recebimentos lançados no período. Nunca é inferida do contrato.
 * - Margem: receita considerada (prevista ou recebida, conforme parâmetro)
 *   − despesas de natureza variável − comissões de indicadores do período.
 *
 * $executiveIds: null = todos (inclui despesas gerais, sem executivo).
 */
final class FinanceMetrics
{
    /** @param list<int>|null $executiveIds */
    public function __construct(private Period $period, private ?array $executiveIds = null, private ?int $regionId = null) {}

    /**
     * @return array{operational: string, travel: string, pending: string, pending_count: int, visits_done: int, contracts_signed: int,
     *     cost_per_visit: string|null, cost_per_contract: string|null, contracted: string, expected_revenue: string, received: string,
     *     variable_costs: string, commissions: string, margin_basis: string, margin_revenue: string, margin: string, margin_pct: float|null}
     */
    public function summary(): array
    {
        $operational = Money::of($this->costs()->sum('expenses.amount'));
        $travel = Money::of($this->costs()->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->where('expense_categories.is_travel', true)->sum('expenses.amount'));
        $variable = Money::of($this->costs()->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')->where('expense_categories.nature', 'variable')->sum('expenses.amount'));
        $pending = $this->expenses()->where('expenses.status', ExpenseStatus::Pending->value);

        $visits = $this->visitsDone()->count();
        $contracts = $this->contracts()->count();
        $contracted = Money::of($this->contracts()->sum('contract_value'));
        // Receita prevista por contrato; sem prevista informada, vale o valor do contrato.
        $expectedRevenue = Money::of($this->contracts()->sum(DB::raw('coalesce(expected_revenue, contract_value)')));
        $received = Money::of($this->receipts()->sum('contract_receipts.amount'));
        $commissions = Money::of($this->commissions()->sum(DB::raw('coalesce(approved_amount, expected_amount)')));

        $basis = (string) SystemSetting::get('finance.margin_revenue_basis', 'expected');
        $marginRevenue = $basis === 'received' ? $received : $expectedRevenue;
        $margin = Money::sub(Money::sub($marginRevenue, $variable), $commissions);

        return [
            'operational' => $operational,
            'travel' => $travel,
            'pending' => Money::of((clone $pending)->sum('expenses.amount')),
            'pending_count' => (clone $pending)->count(),
            'visits_done' => $visits,
            'contracts_signed' => $contracts,
            'cost_per_visit' => $visits > 0 ? Money::of(bcdiv($operational, (string) $visits, 2)) : null,
            'cost_per_contract' => $contracts > 0 ? Money::of(bcdiv($operational, (string) $contracts, 2)) : null,
            'contracted' => $contracted,
            'expected_revenue' => $expectedRevenue,
            'received' => $received,
            'variable_costs' => $variable,
            'commissions' => $commissions,
            'margin_basis' => $basis,
            'margin_revenue' => $marginRevenue,
            'margin' => $margin,
            'margin_pct' => (float) $marginRevenue > 0 ? round((float) $margin / (float) $marginRevenue * 100, 1) : null,
        ];
    }

    /**
     * @return list<array{category: string, total: string, share: float|null, nature: string}>
     */
    public function byCategory(): array
    {
        $rows = $this->costs()->select('expenses.expense_category_id')->selectRaw('sum(expenses.amount) as total')
            ->groupBy('expenses.expense_category_id')->toBase()->get();
        $sum = (float) $rows->sum('total');
        $categories = ExpenseCategory::query()->get()->keyBy('id');

        return array_values($rows->map(fn ($r): array => [
            'category' => (string) ($categories[$r->expense_category_id]->name ?? 'Categoria removida'),
            'total' => Money::of($r->total),
            'share' => FunnelMetrics::pct((float) $r->total, $sum),
            'nature' => (string) ($categories[$r->expense_category_id]->nature ?? 'variable'),
        ])->sortByDesc(fn ($r) => (float) $r['total'])->all());
    }

    /**
     * Receita contratada por canal de aquisição (origem do contrato).
     *
     * @return list<array{source: string, total: string, contracts: int, share: float|null}>
     */
    public function revenueBySource(): array
    {
        $rows = $this->contracts()->select('contracts.lead_source_id')
            ->selectRaw('sum(contract_value) as total, count(*) as n')->groupBy('contracts.lead_source_id')->toBase()->get();
        $sum = (float) $rows->sum('total');
        $names = LeadSource::query()->pluck('name', 'id');

        return array_values($rows->map(fn ($r): array => [
            'source' => $r->lead_source_id ? (string) ($names[$r->lead_source_id] ?? 'Origem removida') : 'Sem origem',
            'total' => Money::of($r->total),
            'contracts' => (int) $r->n,
            'share' => FunnelMetrics::pct((float) $r->total, $sum),
        ])->sortByDesc(fn ($r) => (float) $r['total'])->all());
    }

    /**
     * Custos por região (região do executivo; despesa geral usa a região da cidade).
     *
     * @return list<array{region: string, cost: string, visits: int, contracts: int, cost_per_visit: string|null, cost_per_contract: string|null}>
     */
    public function byRegion(): array
    {
        $costs = $this->costs()->select('expenses.region_id')->selectRaw('sum(expenses.amount) as total')->groupBy('expenses.region_id')->toBase()->get()->keyBy('region_id');
        $visits = $this->visitsDone()->join('executives', 'executives.id', '=', 'visits.executive_id')
            ->select('executives.region_id')->selectRaw('count(*) as n')->groupBy('executives.region_id')->toBase()->get()->keyBy('region_id');
        $contracts = $this->contracts()->join('executives', 'executives.id', '=', 'contracts.executive_id')
            ->select('executives.region_id')->selectRaw('count(*) as n')->groupBy('executives.region_id')->toBase()->get()->keyBy('region_id');

        $ids = collect([...$costs->keys(), ...$visits->keys(), ...$contracts->keys()])->unique();
        $names = Region::withTrashed()->pluck('name', 'id');

        return array_values($ids->map(function ($id) use ($costs, $visits, $contracts, $names): array {
            $cost = Money::of($costs[$id]->total ?? 0);
            $v = (int) ($visits[$id]->n ?? 0);
            $c = (int) ($contracts[$id]->n ?? 0);

            return [
                'region' => $id ? (string) ($names[$id] ?? 'Região removida') : 'Sem região',
                'cost' => $cost,
                'visits' => $v,
                'contracts' => $c,
                'cost_per_visit' => $v > 0 ? Money::of(bcdiv($cost, (string) $v, 2)) : null,
                'cost_per_contract' => $c > 0 ? Money::of(bcdiv($cost, (string) $c, 2)) : null,
            ];
        })->sortByDesc(fn ($r) => (float) $r['cost'])->all());
    }

    /**
     * @return list<array{executive: Executive, cost: string, visits: int, contracts: int, contracted: string, cost_per_visit: string|null, cost_per_contract: string|null}>
     */
    public function byExecutive(): array
    {
        $costs = $this->costs()->whereNotNull('expenses.executive_id')->select('expenses.executive_id')->selectRaw('sum(expenses.amount) as total')->groupBy('expenses.executive_id')->toBase()->get()->keyBy('executive_id');
        $visits = $this->visitsDone()->select('visits.executive_id')->selectRaw('count(*) as n')->groupBy('visits.executive_id')->toBase()->get()->keyBy('executive_id');
        $contracts = $this->contracts()->select('contracts.executive_id')->selectRaw('count(*) as n, sum(contract_value) as total')->groupBy('contracts.executive_id')->toBase()->get()->keyBy('executive_id');

        $ids = collect([...$costs->keys(), ...$visits->keys(), ...$contracts->keys()])->unique()->filter();
        /** @var array<int, Executive> $executives */
        $executives = Executive::withTrashed()->with('user')->whereKey($ids)->get()->keyBy('id')->all();

        return array_values($ids->filter(fn ($id) => isset($executives[(int) $id]))->map(function ($id) use ($costs, $visits, $contracts, $executives): array {
            $cost = Money::of($costs[$id]->total ?? 0);
            $v = (int) ($visits[$id]->n ?? 0);
            $c = (int) ($contracts[$id]->n ?? 0);

            return [
                'executive' => $executives[(int) $id],
                'cost' => $cost,
                'visits' => $v,
                'contracts' => $c,
                'contracted' => Money::of($contracts[$id]->total ?? 0),
                'cost_per_visit' => $v > 0 ? Money::of(bcdiv($cost, (string) $v, 2)) : null,
                'cost_per_contract' => $c > 0 ? Money::of(bcdiv($cost, (string) $c, 2)) : null,
            ];
        })->sortBy(fn ($r) => $r['executive']->user->name)->all());
    }

    /**
     * Evolução mensal dos últimos meses até o fim do período: custos, receita contratada e margem.
     *
     * @return array{labels: list<string>, costs: list<float>, revenue: list<float>, margin: list<float>}
     */
    public function monthly(int $months = 6): array
    {
        $end = $this->period->end->startOfMonth();
        $out = ['labels' => [], 'costs' => [], 'revenue' => [], 'margin' => []];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $end->subMonthsNoOverflow($i);
            $m = new self(Period::custom($month->startOfMonth(), $month->endOfMonth()), $this->executiveIds, $this->regionId);
            $s = $m->summary();
            $out['labels'][] = ucfirst($month->translatedFormat('M/y'));
            $out['costs'][] = (float) $s['operational'];
            $out['revenue'][] = (float) $s['contracted'];
            $out['margin'][] = (float) $s['margin'];
        }

        return $out;
    }

    public function previous(): self
    {
        return new self($this->period->previous(), $this->executiveIds, $this->regionId);
    }

    // ---------- Consultas base ----------

    /** @return Builder<Expense> */
    private function expenses(): Builder
    {
        return Expense::query()
            ->where('expenses.kind', ExpenseKind::Actual->value)
            ->whereBetween('expenses.spent_on', $this->dates())
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('expenses.executive_id', $this->executiveIds ?: [0]))
            ->when($this->regionId, fn ($q) => $q->where('expenses.region_id', $this->regionId));
    }

    /** @return Builder<Expense> */
    private function costs(): Builder
    {
        return $this->expenses()->where('expenses.status', ExpenseStatus::Approved->value);
    }

    /** @return Builder<Visit> */
    private function visitsDone(): Builder
    {
        return Visit::query()
            ->where('visits.status', VisitStatus::Done->value)
            ->whereBetween('visits.scheduled_at', $this->period->range())
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('visits.executive_id', $this->executiveIds ?: [0]))
            ->when($this->regionId, fn ($q) => $q->whereIn('visits.executive_id', Executive::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return Builder<Contract> */
    private function contracts(): Builder
    {
        return Contract::query()
            ->where('contracts.status', ContractStatus::Signed->value)
            ->whereBetween('contracts.signed_at', $this->dates())
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('contracts.executive_id', $this->executiveIds ?: [0]))
            ->when($this->regionId, fn ($q) => $q->whereIn('contracts.executive_id', Executive::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return Builder<ContractReceipt> */
    private function receipts(): Builder
    {
        return ContractReceipt::query()
            ->join('contracts', 'contracts.id', '=', 'contract_receipts.contract_id')
            ->whereNull('contracts.deleted_at')
            ->whereBetween('contract_receipts.received_at', $this->dates())
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('contracts.executive_id', $this->executiveIds ?: [0]))
            ->when($this->regionId, fn ($q) => $q->whereIn('contracts.executive_id', Executive::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return Builder<Commission> */
    private function commissions(): Builder
    {
        return Commission::query()
            ->where('status', '!=', CommissionStatus::Cancelled->value)
            ->whereBetween('generated_at', $this->dates())
            ->when($this->executiveIds !== null || $this->regionId, fn ($q) => $q->whereIn('contract_id', $this->contractsAll()->select('contracts.id')));
    }

    /** @return Builder<Contract> */
    private function contractsAll(): Builder
    {
        return Contract::query()
            ->when($this->executiveIds !== null, fn ($q) => $q->whereIn('contracts.executive_id', $this->executiveIds ?: [0]))
            ->when($this->regionId, fn ($q) => $q->whereIn('contracts.executive_id', Executive::withTrashed()->where('region_id', $this->regionId)->select('id')));
    }

    /** @return array{0: string, 1: string} */
    private function dates(): array
    {
        return [$this->period->start->toDateString(), $this->period->end->toDateString()];
    }
}
