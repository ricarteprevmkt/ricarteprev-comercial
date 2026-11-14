<?php

namespace App\Reports\Definitions;

use App\Metrics\FinanceMetrics;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 9. Custos por executivo. */
final class CostsByExecutiveReport extends Report
{
    public function key(): string
    {
        return 'custos-executivo';
    }

    public function title(): string
    {
        return 'Custos por executivo';
    }

    public function description(): string
    {
        return 'Despesas aprovadas, visitas, contratos e custo unitário de cada executivo.';
    }

    public function filters(): array
    {
        return ['executive', 'region'];
    }

    public function columns(): array
    {
        return [
            'name' => ['Executivo', 'text'],
            'cost' => ['Custos', 'money'],
            'visits' => ['Visitas realizadas', 'int'],
            'contracts' => ['Contratos', 'int'],
            'cost_per_visit' => ['Custo/visita', 'money'],
            'cost_per_contract' => ['Custo/contrato', 'money'],
            'contracted' => ['Receita contratada', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        return array_map(fn ($r) => [
            'name' => $r['executive']->user->name,
            'cost' => $r['cost'],
            'visits' => $r['visits'],
            'contracts' => $r['contracts'],
            'cost_per_visit' => $r['cost_per_visit'],
            'cost_per_contract' => $r['cost_per_contract'],
            'contracted' => $r['contracted'],
        ], (new FinanceMetrics($filters->period, $filters->executiveIds()))->byExecutive());
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['cost', 'visits', 'contracts', 'contracted'], 'name');
    }
}
