<?php

namespace App\Enums;

enum VisitType: string
{
    case FieldVisit = 'field_visit';
    case Appointment = 'appointment';
    case Return = 'return';
    case FollowUp = 'follow_up';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::FieldVisit => 'Visita externa',
            self::Appointment => 'Atendimento agendado',
            self::Return => 'Retorno',
            self::FollowUp => 'Follow-up',
            self::Meeting => 'Reunião',
        };
    }

    /** Atividades que exigem deslocamento e entram na rota do dia. */
    public function isOnSite(): bool
    {
        return in_array($this, [self::FieldVisit, self::Appointment, self::Return, self::Meeting], true);
    }
}
