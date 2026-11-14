<?php

namespace App\Metrics;

use App\Enums\ContractStatus;
use App\Enums\OpportunityStage;
use App\Models\Catalogs\LeadSource;
use App\Models\Catalogs\LossReason;
use App\Models\Contract;
use App\Models\Executive;
use App\Models\Opportunity;
use App\Support\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas do funil comercial. As fórmulas estão em docs/metricas.md.
 *
 * - Base (coorte): oportunidades criadas no período, exceto "Em acompanhamento".
 * - Chegou à etapa X: furthest_stage_rank >= rank(X), ou seja, conta etapas puladas.
 * - Contratos assinados: contratos com status assinado e signed_at no período.
 *
 * $executiveIds: null = todos; lista = só esses executivos (recorte por permissão).
 */
final class FunnelMetrics
{
    /** @param list<int>|null $executiveIds */
    public function __construct(private Period $period, private ?array $executiveIds = null) {}

    /**
     * @return array{base: int, stages: list<array{stage: OpportunityStage, count: int, share: float|null, conversion: float|null}>, overall: float|null}
     */
    public function funnel(): array
    {
        $row = $this->cohort()
            ->selectRaw('count(*) as base')
            ->selectRaw('sum(furthest_stage_rank >= 1) as r1, sum(furthest_stage_rank >= 2) as r2, sum(furthest_stage_rank >= 3) as r3, sum(furthest_stage_rank >= 4) as r4, sum(furthest_stage_rank >= 5) as r5')
            ->toBase()
            ->first();

        $base = (int) ($row->base ?? 0);
        $stages = [];
        $previous = null;

        foreach (OpportunityStage::funnel() as $stage) {
            $count = (int) ($row->{'r'.$stage->rank()} ?? 0);
            $stages[] = [
                'stage' => $stage,
                'count' => $count,
                'share' => self::pct($count, $base),
                'conversion' => $previous === null ? null : self::pct($count, $previous),
            ];
            $previous = $count;
        }

        return ['base' => $base, 'stages' => $stages, 'overall' => self::pct($stages[4]['count'], $base)];
    }

    public function contractsSigned(): int
    {
        return $this->signedContracts()->count();
    }

    public function contractedRevenue(): string
    {
        return (string) ($this->signedContracts()->sum('contract_value') ?: '0.00');
    }

    /** Média de dias entre a abertura da oportunidade e a assinatura, dos contratos assinados no período. */
    public function averageDaysToSign(): ?float
    {
        $avg = $this->signedContracts()
            ->join('opportunities', 'opportunities.id', '=', 'contracts.opportunity_id')
            ->avg(DB::raw('datediff(contracts.signed_at, opportunities.created_at)'));

        return $avg === null ? null : round((float) $avg, 1);
    }

    /**
     * @return list<array{executive: Executive, base: int, qualified: int, scheduled: int, proposals: int, signed: int, conversion: float|null}>
     */
    public function byExecutive(): array
    {
        $rows = $this->cohort()
            ->select('executive_id')
            ->selectRaw('count(*) as base, sum(furthest_stage_rank >= 2) as r2, sum(furthest_stage_rank >= 3) as r3, sum(furthest_stage_rank >= 4) as r4, sum(furthest_stage_rank >= 5) as r5')
            ->groupBy('executive_id')
            ->toBase()
            ->get();

        /** @var array<int, Executive> $executives */
        $executives = Executive::withTrashed()->with('user')->whereKey($rows->pluck('executive_id'))->get()->keyBy('id')->all();

        return array_values($rows->map(fn ($r): array => [
            'executive' => $executives[(int) $r->executive_id],
            'base' => (int) $r->base,
            'qualified' => (int) $r->r2,
            'scheduled' => (int) $r->r3,
            'proposals' => (int) $r->r4,
            'signed' => (int) $r->r5,
            'conversion' => self::pct((int) $r->r5, (int) $r->base),
        ])->sortByDesc('conversion')->all());
    }

    /**
     * @return list<array{source: string, base: int, signed: int, share: float|null, conversion: float|null}>
     */
    public function bySource(): array
    {
        $base = $this->cohort()->count();
        $rows = $this->cohort()
            ->select('lead_source_id')
            ->selectRaw('count(*) as base, sum(furthest_stage_rank >= 5) as signed')
            ->groupBy('lead_source_id')
            ->toBase()
            ->get();

        $names = self::names(LeadSource::query()->get(['id', 'name']));

        return array_values($rows->map(fn ($r): array => [
            'source' => $r->lead_source_id ? ($names[(int) $r->lead_source_id] ?? 'Origem removida') : 'Sem origem',
            'base' => (int) $r->base,
            'signed' => (int) $r->signed,
            'share' => self::pct((int) $r->base, $base),
            'conversion' => self::pct((int) $r->signed, (int) $r->base),
        ])->sortByDesc('base')->all());
    }

    /**
     * Motivos das oportunidades encerradas sem contrato no período.
     *
     * @return list<array{reason: string, count: int, share: float|null}>
     */
    public function lossReasons(): array
    {
        $rows = $this->scoped(Opportunity::query())
            ->whereIn('stage', [OpportunityStage::Lost->value, OpportunityStage::Disqualified->value])
            ->whereBetween('closed_at', $this->period->range())
            ->select('loss_reason_id')->selectRaw('count(*) as total')
            ->groupBy('loss_reason_id')
            ->toBase()->get();

        $total = (int) $rows->sum('total');
        $names = self::names(LossReason::query()->get(['id', 'name']));

        return array_values($rows->map(fn ($r): array => [
            'reason' => $r->loss_reason_id ? ($names[(int) $r->loss_reason_id] ?? 'Motivo removido') : 'Sem motivo',
            'count' => (int) $r->total,
            'share' => self::pct((int) $r->total, $total),
        ])->sortByDesc('count')->all());
    }

    /** Mesmas métricas para o período anterior, para comparação. */
    public function previous(): self
    {
        return new self($this->period->previous(), $this->executiveIds);
    }

    /** @return Builder<Opportunity> */
    private function cohort(): Builder
    {
        return $this->scoped(Opportunity::query())
            ->whereBetween('opportunities.created_at', $this->period->range())
            ->where('stage', '!=', OpportunityStage::FollowUp->value);
    }

    /** @return Builder<Contract> */
    private function signedContracts(): Builder
    {
        return $this->scoped(Contract::query())
            ->where('contracts.status', ContractStatus::Signed->value)
            ->whereBetween('contracts.signed_at', [$this->period->start->toDateString(), $this->period->end->toDateString()]);
    }

    /**
     * @template T of Opportunity|Contract
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scoped(Builder $query): Builder
    {
        if ($this->executiveIds === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->qualifyColumn('executive_id'), $this->executiveIds === [] ? [0] : $this->executiveIds);
    }

    /**
     * @param  iterable<LeadSource|LossReason>  $items
     * @return array<int, string>
     */
    private static function names(iterable $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $names[$item->id] = $item->name;
        }

        return $names;
    }

    public static function pct(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /** Variação percentual entre dois valores (null quando não há base de comparação). */
    public static function delta(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || (float) $previous === 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
