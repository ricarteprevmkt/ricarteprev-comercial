<?php

namespace App\Enums;

/**
 * Resultado de um processo no INSS, como o comercial precisa enxergar.
 * O C2 devolve a situação em texto livre; a classificação é feita em
 * InssOutcome::classify().
 */
enum InssOutcome: string
{
    case InProgress = 'in_progress';
    case Granted = 'granted';
    case Denied = 'denied';
    case Concluded = 'concluded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Em andamento',
            self::Granted => 'Deferido',
            self::Denied => 'Indeferido',
            self::Concluded => 'Concluído',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'sky',
            self::Granted => 'green',
            self::Denied => 'red',
            self::Concluded => 'zinc',
        };
    }

    /** "Indeferido" é testado antes, porque contém "deferido". */
    public static function classify(?string $decision, ?string $status, bool $concluded): self
    {
        $text = mb_strtolower(trim(($decision ?? '').' '.($status ?? '')));

        return match (true) {
            str_contains($text, 'indefer') || str_contains($text, 'negad') => self::Denied,
            str_contains($text, 'defer') || str_contains($text, 'concedid') => self::Granted,
            $concluded || str_contains($text, 'conclu') || str_contains($text, 'arquivad') => self::Concluded,
            default => self::InProgress,
        };
    }
}
