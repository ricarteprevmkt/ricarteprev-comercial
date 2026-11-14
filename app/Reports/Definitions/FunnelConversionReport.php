<?php

namespace App\Reports\Definitions;

use App\Metrics\FunnelMetrics;
use App\Reports\Report;
use App\Reports\ReportFilters;

/** 6. Conversão do funil, por executivo, com total. */
final class FunnelConversionReport extends Report
{
    public function key(): string
    {
        return 'conversao-funil';
    }

    public function title(): string
    {
        return 'Conversão do funil';
    }

    public function description(): string
    {
        return 'Oportunidades abertas no período e até onde chegaram, por executivo.';
    }

    public function filters(): array
    {
        return ['executive', 'region'];
    }

    public function columns(): array
    {
        return [
            'name' => ['Executivo', 'text'],
            'base' => ['Contatos', 'int'],
            'qualified' => ['Qualificados', 'int'],
            'scheduled' => ['Agendados', 'int'],
            'proposals' => ['Propostas', 'int'],
            'signed' => ['Contratos', 'int'],
            'conversion' => ['Conversão', 'pct'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $rows = array_map(fn ($r) => [
            'name' => $r['executive']->user->name,
            'base' => $r['base'],
            'qualified' => $r['qualified'],
            'scheduled' => $r['scheduled'],
            'proposals' => $r['proposals'],
            'signed' => $r['signed'],
            'conversion' => $r['conversion'],
        ], (new FunnelMetrics($filters->period, $filters->executiveIds()))->byExecutive());

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        if ($rows === []) {
            return null;
        }

        $t = $this->sum($rows, ['base', 'qualified', 'scheduled', 'proposals', 'signed'], 'name');
        $t['conversion'] = FunnelMetrics::pct($t['signed'], $t['base']);

        return $t;
    }
}
