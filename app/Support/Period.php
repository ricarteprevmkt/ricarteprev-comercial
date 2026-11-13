<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Período de análise usado por todos os painéis e relatórios.
 *
 * Datas são sempre dias inteiros no fuso da aplicação: start às 00:00:00 e
 * end às 23:59:59. O período anterior tem a mesma quantidade de dias e
 * termina no dia imediatamente antes do início deste.
 */
final readonly class Period
{
    public const SESSION_KEY = 'period';

    public const DEFAULT_PRESET = '30d';

    /** @var array<string, string> */
    public const PRESETS = [
        'hoje' => 'Hoje',
        '7d' => 'Últimos 7 dias',
        '30d' => 'Últimos 30 dias',
        'mes_atual' => 'Este mês',
        'mes_anterior' => 'Mês anterior',
        'trimestre' => 'Últimos 3 meses',
        'ano' => 'Este ano',
        'personalizado' => 'Personalizado',
    ];

    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $preset,
    ) {}

    public static function fromPreset(string $preset, ?CarbonImmutable $today = null): self
    {
        $today = ($today ?? CarbonImmutable::now())->startOfDay();

        [$start, $end] = match ($preset) {
            'hoje' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            '30d' => [$today->subDays(29), $today],
            'mes_atual' => [$today->startOfMonth(), $today],
            'mes_anterior' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'trimestre' => [$today->subMonthsNoOverflow(3)->addDay(), $today],
            'ano' => [$today->startOfYear(), $today],
            default => throw new InvalidArgumentException("Período desconhecido: {$preset}"),
        };

        return new self($start->startOfDay(), $end->endOfDay(), $preset);
    }

    public static function custom(string|CarbonImmutable $start, string|CarbonImmutable $end): self
    {
        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = CarbonImmutable::parse($end)->endOfDay();

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('A data final não pode ser anterior à data inicial.');
        }

        return new self($start, $end, 'personalizado');
    }

    /**
     * Período salvo na sessão do usuário, ou o padrão (últimos 30 dias).
     */
    public static function current(): self
    {
        $stored = session(self::SESSION_KEY);

        try {
            if (is_array($stored) && ($stored['preset'] ?? null) === 'personalizado') {
                return self::custom($stored['start'], $stored['end']);
            }

            if (is_array($stored) && isset(self::PRESETS[$stored['preset'] ?? ''])) {
                return self::fromPreset($stored['preset']);
            }
        } catch (InvalidArgumentException) {
            // Sessão com valor inválido: volta para o padrão.
        }

        return self::fromPreset(self::DEFAULT_PRESET);
    }

    public function remember(): void
    {
        session([self::SESSION_KEY => [
            'preset' => $this->preset,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ]]);
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end->startOfDay()) + 1;
    }

    public function previous(): self
    {
        $end = $this->start->subDay();

        return new self($end->subDays($this->days() - 1)->startOfDay(), $end->endOfDay(), 'personalizado');
    }

    public function previousYear(): self
    {
        return new self($this->start->subYearNoOverflow(), $this->end->subYearNoOverflow(), 'personalizado');
    }

    public function label(): string
    {
        if ($this->preset !== 'personalizado') {
            return self::PRESETS[$this->preset];
        }

        return $this->start->isSameDay($this->end)
            ? $this->start->format('d/m/Y')
            : $this->start->format('d/m/Y').' a '.$this->end->format('d/m/Y');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        return [$this->start, $this->end];
    }
}
