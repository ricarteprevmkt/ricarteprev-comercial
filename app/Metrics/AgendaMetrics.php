<?php

namespace App\Metrics;

use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Enums\VisitStatus;
use App\Models\Expense;
use App\Models\Route;
use App\Models\Visit;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resumo da agenda em um intervalo de datas (normalmente um dia).
 * Valores estimados (rota) e registrados (km informado, despesas) ficam separados.
 */
final class AgendaMetrics
{
    /** @param list<int>|null $executiveIds null = todos */
    public function __construct(
        private CarbonImmutable $from,
        private CarbonImmutable $to,
        private ?array $executiveIds = null,
    ) {}

    /** @param list<int>|null $executiveIds */
    public static function day(CarbonImmutable $date, ?array $executiveIds = null): self
    {
        return new self($date->startOfDay(), $date->endOfDay(), $executiveIds);
    }

    /**
     * @return array{planned: int, done: int, pending: int, not_done: int, without_result: int,
     *     km_estimated: float, km_reported: float, cost_estimated: string|null, expenses_actual: string, approximate: bool, by_road: bool}
     */
    public function summary(): array
    {
        $counts = $this->visits()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (VisitStatus ...$s) => (int) collect($s)->sum(fn (VisitStatus $x) => (int) ($counts[$x->value] ?? 0));

        $routes = $this->scoped(Route::query())->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()]);
        $kmEstimated = (float) (clone $routes)->sum('km_estimated');

        return [
            'planned' => $count(VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress, VisitStatus::Done, VisitStatus::NotDone),
            'done' => $count(VisitStatus::Done),
            'pending' => $count(VisitStatus::Scheduled, VisitStatus::Confirmed, VisitStatus::InProgress),
            'not_done' => $count(VisitStatus::NotDone),
            'without_result' => $this->scoped(Visit::query())->withoutResult()->whereBetween('scheduled_at', [$this->from, $this->to])->count(),
            'km_estimated' => round($kmEstimated, 1),
            'km_reported' => round((float) $this->visits()->where('status', VisitStatus::Done->value)->sum('km_reported'), 1),
            'cost_estimated' => Geo::estimatedCost($kmEstimated),
            'expenses_actual' => (string) ($this->scoped(Expense::query())
                ->where('kind', ExpenseKind::Actual->value)
                ->where('status', '!=', ExpenseStatus::Rejected->value)
                ->whereBetween('spent_on', [$this->from->toDateString(), $this->to->toDateString()])
                ->sum('amount') ?: '0'),
            'approximate' => (clone $routes)->where('has_approximate_points', true)->exists(),
            // Todas as rotas do intervalo calculadas pelas estradas (nenhuma pelo fator).
            'by_road' => (clone $routes)->where('km_source', 'road')->exists() && ! (clone $routes)->where('km_source', 'estimate')->exists(),
        ];
    }

    /** @return Builder<Visit> */
    private function visits(): Builder
    {
        return $this->scoped(Visit::query())->whereBetween('scheduled_at', [$this->from, $this->to]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
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
}
