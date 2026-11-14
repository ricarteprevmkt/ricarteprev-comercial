<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Done = 'done';
    case NotDone = 'not_done';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'Em andamento',
            self::Done => 'Concluída',
            self::NotDone => 'Não realizada',
            self::Cancelled => 'Cancelada',
            self::Rescheduled => 'Reagendada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'zinc',
            self::Confirmed => 'sky',
            self::InProgress => 'blue',
            self::Done => 'green',
            self::NotDone => 'red',
            self::Cancelled, self::Rescheduled => 'amber',
        };
    }

    /** Cor do marcador no mapa (hex, para o Leaflet). */
    public function mapColor(): string
    {
        return match ($this) {
            self::Done => '#009a66',
            self::InProgress => '#2f6fd0',
            self::NotDone => '#dc2626',
            self::Cancelled, self::Rescheduled => '#a1a1aa',
            default => '#e9a83a',
        };
    }

    /** Ainda pode ser realizada. */
    public function isPending(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::InProgress], true);
    }

    /** Conta como planejada (fica fora o que foi cancelado ou substituído por reagendamento). */
    public function isPlanned(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Rescheduled], true);
    }
}
