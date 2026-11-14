<?php

namespace App\Reports;

/**
 * Lista dos relatórios disponíveis, na ordem em que aparecem na tela.
 */
final class ReportRegistry
{
    /** @return list<class-string<Report>> */
    public static function classes(): array
    {
        return [
            Definitions\GeneralPerformanceReport::class,
            Definitions\ExecutivesReport::class,
            Definitions\VisitsDoneReport::class,
            Definitions\VisitsNotDoneReport::class,
            Definitions\OpportunitiesReport::class,
            Definitions\FunnelConversionReport::class,
            Definitions\ContractsReport::class,
            Definitions\ExpensesReport::class,
            Definitions\CostsByExecutiveReport::class,
            Definitions\CostsByRegionReport::class,
            Definitions\RegionalIndicatorsReport::class,
            Definitions\CommissionsReport::class,
        ];
    }

    /** @return list<Report> */
    public static function all(): array
    {
        return array_map(fn (string $class) => new $class, self::classes());
    }

    public static function find(string $key): ?Report
    {
        foreach (self::all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }
}
