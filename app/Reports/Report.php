<?php

namespace App\Reports;

/**
 * Definição de um relatório: colunas, linhas e (opcional) linha de totais e
 * resumo. A mesma definição alimenta a tela, o PDF, o Excel e o CSV.
 *
 * Formatos de coluna: text, int, money, pct, date, decimal.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Filtros que fazem sentido neste relatório.
     *
     * @return list<'executive'|'indicator'|'city'|'region'|'source'|'stage'>
     */
    abstract public function filters(): array;

    /** @return array<string, array{0: string, 1: string}> chave => [rótulo, formato] */
    abstract public function columns(): array;

    /** @return array<int, array<string, mixed>> */
    abstract public function rows(ReportFilters $filters): array;

    /**
     * Linha de totais (mesmas chaves das colunas), ou null.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    public function totals(array $rows, ReportFilters $filters): ?array
    {
        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function sum(array $rows, array $keys, string $labelKey, string $label = 'Total'): array
    {
        $total = [$labelKey => $label];
        foreach ($keys as $key) {
            $total[$key] = array_sum(array_map(fn ($r) => (float) ($r[$key] ?? 0), $rows));
        }

        return $total;
    }

    /** Formata um valor para exibição (tela e PDF). */
    public static function format(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'money' => 'R$ '.number_format((float) $value, 2, ',', '.'),
            'pct' => number_format((float) $value, 1, ',', '.').'%',
            'int' => number_format((float) $value, 0, ',', '.'),
            'decimal' => number_format((float) $value, 1, ',', '.'),
            'date' => $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value,
            default => (string) $value,
        };
    }

    /** Valor bruto para planilha (números como números). */
    public static function raw(mixed $value, string $format): mixed
    {
        if ($value === null) {
            return '';
        }

        return match ($format) {
            'money', 'pct', 'decimal' => round((float) $value, 2),
            'int' => (int) $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : (string) $value,
            default => (string) $value,
        };
    }
}
