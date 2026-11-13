<?php

namespace App\Enums;

enum ExecutiveStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::OnLeave => 'Afastado',
            self::Terminated => 'Desligado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::OnLeave => 'amber',
            self::Terminated => 'zinc',
        };
    }

    /** O acesso ao sistema acompanha o status: desligado perde o login. */
    public function allowsLogin(): bool
    {
        return $this !== self::Terminated;
    }
}
