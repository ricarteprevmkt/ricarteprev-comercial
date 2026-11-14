<?php

namespace App\Reports\Definitions;

use App\Metrics\AgendaMetrics;
use App\Metrics\FinanceMetrics;
use App\Metrics\FunnelMetrics;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Support\Period;

/** 1. Desempenho comercial geral: período x período anterior. */
final class GeneralPerformanceReport extends Report
{
    public function key(): string
    {
        return 'desempenho-geral';
    }

    public function title(): string
    {
        return 'Desempenho comercial geral';
    }

    public function description(): string
    {
        return 'Principais indicadores do período comparados com o período anterior.';
    }

    public function filters(): array
    {
        return ['executive', 'region'];
    }

    public function columns(): array
    {
        return [
            'indicator' => ['Indicador', 'text'],
            'current' => ['Período', 'text'],
            'previous' => ['Período anterior', 'text'],
            'change' => ['Variação', 'pct'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $now = $this->collect($filters->period, $filters);
        $before = $this->collect($filters->period->previous(), $filters);
        $rows = [];

        foreach ($now as $label => [$value, $format]) {
            $prev = $before[$label][0];
            $rows[] = [
                'indicator' => $label,
                'current' => self::format($value, $format),
                'previous' => self::format($prev, $format),
                'change' => $value === null || $prev === null ? null : FunnelMetrics::delta((float) $value, (float) $prev),
            ];
        }

        return $rows;
    }

    /** @return array<string, array{0: int|float|string|null, 1: string}> */
    private function collect(Period $period, ReportFilters $filters): array
    {
        $ids = $filters->executiveIds();
        $agenda = (new AgendaMetrics($period->start, $period->end, $ids))->summary();
        $funnel = (new FunnelMetrics($period, $ids))->funnel();
        $finance = (new FinanceMetrics($period, $ids))->summary();
        $s = $funnel['stages'];

        return [
            'Visitas planejadas' => [$agenda['planned'], 'int'],
            'Visitas realizadas' => [$agenda['done'], 'int'],
            'Visitas não realizadas' => [$agenda['not_done'], 'int'],
            'Contatos (oportunidades abertas)' => [$funnel['base'], 'int'],
            'Qualificados' => [$s[1]['count'], 'int'],
            'Atendimentos agendados' => [$s[2]['count'], 'int'],
            'Propostas apresentadas' => [$s[3]['count'], 'int'],
            'Contratos (da coorte)' => [$s[4]['count'], 'int'],
            'Conversão geral' => [$funnel['overall'], 'pct'],
            'Contratos assinados no período' => [$finance['contracts_signed'], 'int'],
            'Receita contratada' => [$finance['contracted'], 'money'],
            'Receita recebida' => [$finance['received'], 'money'],
            'Custo operacional' => [$finance['operational'], 'money'],
            'Custo por visita' => [$finance['cost_per_visit'], 'money'],
            'Custo por contrato' => [$finance['cost_per_contract'], 'money'],
            'Margem de contribuição estimada' => [$finance['margin'], 'money'],
        ];
    }
}
