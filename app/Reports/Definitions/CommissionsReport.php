<?php

namespace App\Reports\Definitions;

use App\Models\Commission;
use App\Models\RegionalIndicator;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 12. Comissões por parceiro. */
final class CommissionsReport extends Report
{
    public function key(): string
    {
        return 'comissoes';
    }

    public function title(): string
    {
        return 'Comissões por parceiro';
    }

    public function description(): string
    {
        return 'Comissões geradas no período, com regra aplicada, valor previsto, aprovado e pago.';
    }

    public function filters(): array
    {
        return ['indicator', 'region'];
    }

    public function columns(): array
    {
        return [
            'generated' => ['Gerada em', 'text'],
            'indicator' => ['Parceiro', 'text'],
            'client' => ['Cliente', 'text'],
            'contract' => ['Contrato', 'text'],
            'rule' => ['Regra', 'text'],
            'status' => ['Situação', 'text'],
            'expected' => ['Previsto', 'money'],
            'approved' => ['Aprovado', 'money'],
            'paid' => ['Pago', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        return Commission::query()
            ->with(['indicator', 'contract.lead'])
            ->withSum('payments as paid_total', 'amount')
            ->whereBetween('generated_at', $filters->dates())
            ->when($filters->indicatorId, fn ($q) => $q->where('regional_indicator_id', $filters->indicatorId))
            ->when($filters->regionId, fn ($q) => $q->whereIn('regional_indicator_id', RegionalIndicator::withTrashed()->where('region_id', $filters->regionId)->select('id')))
            ->orderBy('generated_at')
            ->get()
            ->map(fn (Commission $c) => [
                'generated' => $c->generated_at->format('d/m/Y'),
                'indicator' => $c->indicator->name,
                'client' => $c->contract->lead->name,
                'contract' => $c->contract->number,
                'rule' => $c->rule_snapshot,
                'status' => $c->status->label(),
                'expected' => $c->expected_amount,
                'approved' => $c->approved_amount,
                'paid' => $c->getAttribute('paid_total') ?? 0,
            ])->values()->all();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['expected', 'approved', 'paid'], 'generated');
    }
}
