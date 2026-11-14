<?php

namespace App\Reports\Definitions;

use App\Enums\ExpenseKind;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 8. Despesas e deslocamentos. */
final class ExpensesReport extends Report
{
    public function key(): string
    {
        return 'despesas';
    }

    public function title(): string
    {
        return 'Despesas e deslocamentos';
    }

    public function description(): string
    {
        return 'Despesas lançadas no período, com situação da aprovação. O total considera só as aprovadas.';
    }

    public function filters(): array
    {
        return ['executive', 'region', 'city'];
    }

    public function columns(): array
    {
        return [
            'date' => ['Data', 'text'],
            'category' => ['Categoria', 'text'],
            'description' => ['Descrição', 'text'],
            'executive' => ['Executivo', 'text'],
            'city' => ['Cidade', 'text'],
            'travel' => ['Deslocamento', 'text'],
            'status' => ['Situação', 'text'],
            'receipt' => ['Comprovante', 'text'],
            'amount' => ['Valor', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        // Região: a gravada na despesa (do executivo, ou da cidade em despesas gerais).
        $ids = (new ReportFilters($filters->period, $filters->executiveId, allowedExecutives: $filters->allowedExecutives))->executiveIds();

        return Expense::query()
            ->with(['category', 'executive.user', 'attachments', 'city'])
            ->where('kind', ExpenseKind::Actual->value)
            ->whereBetween('spent_on', $filters->dates())
            ->when($ids !== null, fn ($q) => $q->whereIn('executive_id', $ids ?: [0]))
            ->when($filters->regionId, fn ($q) => $q->where('region_id', $filters->regionId))
            ->when($filters->cityId, fn ($q) => $q->where('city_id', $filters->cityId))
            ->orderBy('spent_on')
            ->get()
            ->map(fn (Expense $e) => [
                'date' => $e->spent_on->format('d/m/Y'),
                'category' => $e->category->name,
                'description' => $e->description,
                'executive' => $e->executive->user->name ?? 'Geral',
                'city' => $e->city?->name,
                'travel' => $e->category->is_travel ? 'Sim' : 'Não',
                'status' => $e->status->label(),
                'receipt' => $e->attachments->isNotEmpty() ? 'Sim' : 'Não',
                'amount' => $e->amount,
                '_approved' => $e->status === ExpenseStatus::Approved,
            ])->values()->all();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        if ($rows === []) {
            return null;
        }

        $approved = array_filter($rows, fn ($r) => $r['_approved']);

        return ['date' => 'Total aprovado', 'amount' => array_sum(array_map(fn ($r) => (float) $r['amount'], $approved))];
    }
}
