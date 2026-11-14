<?php

namespace App\Reports\Definitions;

use App\Metrics\FinanceMetrics;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 10. Custos por região. */
final class CostsByRegionReport extends Report
{
    public function key(): string
    {
        return 'custos-regiao';
    }

    public function title(): string
    {
        return 'Custos por região';
    }

    public function description(): string
    {
        return 'Despesas aprovadas, visitas e contratos por região do executivo.';
    }

    public function filters(): array
    {
        return ['region'];
    }

    public function columns(): array
    {
        return [
            'region' => ['Região', 'text'],
            'cost' => ['Custos', 'money'],
            'visits' => ['Visitas realizadas', 'int'],
            'contracts' => ['Contratos', 'int'],
            'cost_per_visit' => ['Custo/visita', 'money'],
            'cost_per_contract' => ['Custo/contrato', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->allowedExecutives;

        return (new FinanceMetrics($filters->period, $ids, $filters->regionId))->byRegion();
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['cost', 'visits', 'contracts'], 'region');
    }
}
