<?php

namespace App\Reports\Definitions;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 7. Contratos assinados. */
final class ContractsReport extends Report
{
    public function key(): string
    {
        return 'contratos';
    }

    public function title(): string
    {
        return 'Contratos assinados';
    }

    public function description(): string
    {
        return 'Contratos assinados no período. Valor contratado, receita prevista e recebida em colunas separadas.';
    }

    public function filters(): array
    {
        return ['executive', 'region', 'city', 'source', 'indicator'];
    }

    public function columns(): array
    {
        return [
            'signed_at' => ['Assinatura', 'text'],
            'number' => ['Número', 'text'],
            'client' => ['Cliente', 'text'],
            'executive' => ['Executivo', 'text'],
            'city' => ['Cidade', 'text'],
            'source' => ['Origem', 'text'],
            'indicator' => ['Indicado por', 'text'],
            'value' => ['Valor contratado', 'money'],
            'expected' => ['Receita prevista', 'money'],
            'received' => ['Recebido até hoje', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();

        return Contract::query()
            ->with(['lead.city', 'executive.user', 'source', 'indicator'])
            ->withSum('receipts as received_total', 'amount')
            ->where('status', ContractStatus::Signed->value)
            ->whereBetween('signed_at', $filters->dates())
            ->when($ids !== null, fn ($q) => $q->whereIn('executive_id', $ids ?: [0]))
            ->when($filters->sourceId, fn ($q) => $q->where('lead_source_id', $filters->sourceId))
            ->when($filters->indicatorId, fn ($q) => $q->where('regional_indicator_id', $filters->indicatorId))
            ->when($filters->cityId, fn ($q) => $q->whereHas('lead', fn ($q) => $q->where('city_id', $filters->cityId)))
            ->orderBy('signed_at')
            ->get()
            ->map(fn (Contract $c) => [
                'signed_at' => $c->signed_at?->format('d/m/Y'),
                'number' => $c->number,
                'client' => $c->lead->name,
                'executive' => $c->executive->user->name,
                'city' => $c->lead->city?->name,
                'source' => $c->source?->name,
                'indicator' => $c->indicator?->name,
                'value' => $c->contract_value,
                'expected' => $c->expected_revenue,
                'received' => $c->getAttribute('received_total') ?? 0,
            ])->values()->all();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['value', 'expected', 'received'], 'signed_at', count($rows).' contrato(s)');
    }
}
