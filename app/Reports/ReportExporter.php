<?php

namespace App\Reports;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta um relatório em PDF, Excel (xlsx) ou CSV.
 * O PDF é limitado a PDF_ROW_LIMIT linhas (para volumes maiores, use Excel ou CSV).
 */
final class ReportExporter
{
    public const PDF_ROW_LIMIT = 2000;

    public function export(Report $report, ReportFilters $filters, string $format, User $user): Response
    {
        $rows = $report->rows($filters);
        $totals = $report->totals($rows, $filters);
        $name = Str::slug($report->title()).'-'.$filters->period->start->format('Ymd').'-'.$filters->period->end->format('Ymd');

        activity('relatorios')->causedBy($user)->event('export')
            ->withProperties(['relatorio' => $report->key(), 'formato' => $format, 'filtros' => $filters->toQuery(), 'linhas' => count($rows)])
            ->log("Exportou o relatório {$report->title()} ({$format})");

        return match ($format) {
            'pdf' => $this->pdf($report, $filters, $rows, $totals, $user, $name),
            'xlsx' => $this->sheet($report, $rows, $totals, $name, 'xlsx'),
            default => $this->sheet($report, $rows, $totals, $name, 'csv'),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    private function pdf(Report $report, ReportFilters $filters, array $rows, ?array $totals, User $user, string $name): Response
    {
        $truncated = count($rows) > self::PDF_ROW_LIMIT;

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'filters' => $filters,
            'filterLabels' => ReportFilterLabels::describe($filters),
            'rows' => array_slice($rows, 0, self::PDF_ROW_LIMIT),
            'totals' => $truncated ? null : $totals,
            'truncated' => $truncated,
            'total' => count($rows),
            'user' => $user,
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('images/brand/logo-horizontal.png'))),
        ])
            // Embute só os caracteres usados da fonte: o arquivo cai de ~900 KB para poucas dezenas de KB.
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4', count($report->columns()) > 6 ? 'landscape' : 'portrait')
            ->download($name.'.pdf');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    private function sheet(Report $report, array $rows, ?array $totals, string $name, string $type): StreamedResponse
    {
        $columns = $report->columns();

        return response()->streamDownload(function () use ($columns, $rows, $totals, $type) {
            if ($type === 'csv') {
                // Ponto e vírgula: padrão do Excel em português. BOM: acentos corretos ao abrir no Excel.
                $writer = new CsvWriter(new CsvOptions(FIELD_DELIMITER: ';', SHOULD_ADD_BOM: true));
            } else {
                $writer = new XlsxWriter;
            }

            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $c[0], array_values($columns))));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($key, $c) => Report::raw($row[$key] ?? null, $c[1]), array_keys($columns), array_values($columns))));
            }

            if ($totals) {
                $writer->addRow(Row::fromValues(array_map(fn ($key, $c) => array_key_exists($key, $totals) ? Report::raw($totals[$key], is_string($totals[$key]) ? 'text' : $c[1]) : '', array_keys($columns), array_values($columns))));
            }

            $writer->close();
        }, "{$name}.{$type}", [
            'Content-Type' => $type === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
