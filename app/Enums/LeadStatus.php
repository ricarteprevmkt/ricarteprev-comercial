<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Active = 'active';
    case Client = 'client';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Em negociação',
            self::Client => 'Cliente',
            self::Inactive => 'Inativo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'blue',
            self::Client => 'green',
            self::Inactive => 'zinc',
        };
    }
}
