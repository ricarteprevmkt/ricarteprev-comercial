<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 22mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        header { position: fixed; top: -16mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid #0b234b; }
        header img { height: 10mm; }
        header .title { position: absolute; right: 0; top: 2mm; text-align: right; }
        header .title strong { font-size: 13px; color: #0b234b; display: block; }
        footer { position: fixed; bottom: -11mm; left: 0; right: 0; font-size: 8px; color: #6b7280; }
        footer .page:after { content: counter(page); }
        .filters { margin: 4px 0 10px; }
        .filters span { display: inline-block; margin-right: 12px; }
        .filters b { color: #0b234b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0b234b; color: #fff; text-align: left; padding: 5px 4px; font-weight: bold; }
        td { padding: 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f5f7fb; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #fff3d9 !important; border-top: 1px solid #e9a83a; }
        .note { margin-top: 8px; color: #a8701c; }
        .empty { padding: 20px; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
<header>
    <img src="{{ $logo }}" alt="Ricarte Prev">
    <div class="title"><strong>{{ $report->title() }}</strong>{{ $report->description() }}</div>
</header>
<footer>
    Gerado em {{ now()->format('d/m/Y H:i') }} por {{ $user->name }} · Ricarte Prev · Documento interno com dados pessoais: não compartilhe fora da empresa.
    <span style="float:right">Página <span class="page"></span></span>
</footer>

<div class="filters">
    @foreach ($filterLabels as $label => $value)
        <span><b>{{ $label }}:</b> {{ $value }}</span>
    @endforeach
    <span><b>Registros:</b> {{ $total }}</span>
</div>

@php($columns = $report->columns())
@if (count($rows) === 0)
    <div class="empty">Nenhum registro para os filtros escolhidos.</div>
@else
    <table>
        <thead>
            <tr>
                @foreach ($columns as [$label, $format])
                    <th @class(['num' => in_array($format, ['int', 'money', 'pct', 'decimal'], true)])>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $key => [$label, $format])
                        <td @class(['num' => in_array($format, ['int', 'money', 'pct', 'decimal'], true)])>{{ \App\Reports\Report::format($row[$key] ?? null, $format) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        @if ($totals)
            <tfoot>
                <tr>
                    @foreach ($columns as $key => [$label, $format])
                        <td @class(['num' => in_array($format, ['int', 'money', 'pct', 'decimal'], true)])>
                            @if (array_key_exists($key, $totals))
                                {{ is_string($totals[$key]) ? $totals[$key] : \App\Reports\Report::format($totals[$key], $format) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
    @if ($truncated)
        <p class="note">O PDF mostra as primeiras {{ \App\Reports\ReportExporter::PDF_ROW_LIMIT }} linhas de {{ $total }}. Para a lista completa, exporte em Excel ou CSV.</p>
    @endif
@endif
</body>
</html>
