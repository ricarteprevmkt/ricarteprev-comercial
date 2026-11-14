<?php

namespace App\Reports\Definitions;

use App\Enums\CommissionStatus;
use App\Metrics\FunnelMetrics;
use App\Metrics\ReferralMetrics;
use App\Models\Commission;
use App\Models\CommissionPayment;
use App\Reports\Report;
use App\Reports\ReportFilters;
use Illuminate\Support\Facades\DB;

/** 11. Indicadores regionais. */
final class RegionalIndicatorsReport extends Report
{
    public function key(): string
    {
        return 'indicadores-regionais';
    }

    public function title(): string
    {
        return 'Indicadores regionais';
    }

    public function description(): string
    {
        return 'Indicações válidas, evolução no funil e comissões de cada indicador.';
    }

    public function filters(): array
    {
        return ['region', 'indicator'];
    }

    public function columns(): array
    {
        return [
            'name' => ['Indicador', 'text'],
            'city' => ['Cidade', 'text'],
            'total' => ['Indicações', 'int'],
            'qualified' => ['Qualificadas', 'int'],
            'opportunities' => ['Oportunidades', 'int'],
            'signed' => ['Contratos', 'int'],
            'conversion' => ['Conversão', 'pct'],
            'commission_expected' => ['Comissão prevista', 'money'],
            'commission_paid' => ['Comissão paga', 'money'],
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $metrics = new ReferralMetrics($filters->period, $filters->regionId, $filters->indicatorId);

        return array_map(function ($r) use ($filters) {
            $commissions = Commission::query()->where('regional_indicator_id', $r['indicator']->id)
                ->where('status', '!=', CommissionStatus::Cancelled->value)
                ->whereBetween('generated_at', $filters->dates());

            return [
                'name' => $r['indicator']->name,
                'city' => $r['indicator']->city?->name,
                'total' => $r['total'],
                'qualified' => $r['qualified'],
                'opportunities' => $r['opportunities'],
                'signed' => $r['signed'],
                'conversion' => $r['conversion'],
                'commission_expected' => (clone $commissions)->sum(DB::raw('coalesce(approved_amount, expected_amount)')),
                'commission_paid' => CommissionPayment::query()->whereIn('commission_id', (clone $commissions)->select('id'))->sum('amount'),
            ];
        }, $metrics->byIndicator(1000));
    }

    public function totals(array $rows, ReportFilters $filters): ?array
    {
        if ($rows === []) {
            return null;
        }

        $t = $this->sum($rows, ['total', 'qualified', 'opportunities', 'signed', 'commission_expected', 'commission_paid'], 'name');
        $t['conversion'] = FunnelMetrics::pct($t['signed'], $t['total']);

        return $t;
    }
}
