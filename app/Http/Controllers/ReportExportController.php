<?php

namespace App\Http\Controllers;

use App\Reports\ReportExporter;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, string $report, string $format, ReportExporter $exporter): Response
    {
        abort_unless($request->user()?->can('reports.export'), 403);
        abort_unless(in_array($format, ['pdf', 'xlsx', 'csv'], true), 404);

        $definition = ReportRegistry::find($report);
        abort_if($definition === null, 404);

        $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        set_time_limit(120);

        return $exporter->export($definition, ReportFilters::fromArray($request->query(), $request->user()), $format, $request->user());
    }
}
