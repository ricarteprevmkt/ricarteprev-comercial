<?php

namespace App\Reports\Definitions;

use App\Enums\ExecutiveStatus;
use App\Metrics\ExecutivePerformance;
use App\Models\Executive;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 2. Desempenho individual dos executivos. */
final class ExecutivesReport extends Report
{
    public function key(): string
    {
        return 'executivos';
    }

    public function title(): string
    {
        return 'Desempenho individual dos executivos';
    }

    public function description(): string
    {
        return 'Visitas, oportunidades, contratos, conversão, deslocamento, custos e meta de cada executivo.';
    }

    public function filters(): array
    {
        return ['executive', 'region'];
    }

    public function columns(): array
    {
        return [
            'name' => ['Executivo', 'text'],
            'visits_planned' => ['Visitas planejadas', 'int'],
            'visits_done' => ['Visitas realizadas', 'int'],
            'opportunities' => ['Oportunidades', 'int'],
            'contracts' => ['Contratos', 'int'],
            'conversion' => ['Conversão', 'pct'],
            'km' => ['Km', 'decimal'],
            'cost' => ['Custos', 'money'],
            'cost_per_visit' => ['Custo/visita', 'money'],
            'cost_per_contract' => ['Custo/contrato', 'money'],
            'contracted' => ['Receita contratada', 'money'],
            'goal_visits' => ['Meta de visitas (mês final)', 'text'],
            'goal_contracts' => ['Meta de contratos (mês final)', 'text'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();
        $m = new ExecutivePerformance($filters->period);
        $executives = Executive::query()->with('user')
            ->where('status', '!=', ExecutiveStatus::Terminated->value)
            ->when($ids !== null, fn ($q) => $q->whereKey($ids ?: [0]))
            ->get()->sortBy(fn (Executive $e) => $e->user->name);

        $rows = [];
        foreach ($executives as $e) {
            $s = $m->summary($e);
            $g = $m->goalProgress($e);
            $rows[] = [
                'name' => $e->user->name,
                'visits_planned' => $s['visits_planned'],
                'visits_done' => $s['visits_done'],
                'opportunities' => $s['opportunities'],
                'contracts' => $s['contracts'],
                'conversion' => $s['conversion'],
                'km' => $s['km_reported'] > 0 ? $s['km_reported'] : $s['km_estimated'],
                'cost' => $s['cost'],
                'cost_per_visit' => $s['cost_per_visit'],
                'cost_per_contract' => $s['cost_per_contract'],
                'contracted' => $s['contracted'],
                'goal_visits' => $g['visits_target'] ? "{$g['visits']} de {$g['visits_target']}" : '—',
                'goal_contracts' => $g['contracts_target'] ? "{$g['contracts']} de {$g['contracts_target']}" : '—',
            ];
        }

        return $rows;
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return $rows === [] ? null : $this->sum($rows, ['visits_planned', 'visits_done', 'opportunities', 'contracts', 'km', 'cost', 'contracted'], 'name');
    }
}
