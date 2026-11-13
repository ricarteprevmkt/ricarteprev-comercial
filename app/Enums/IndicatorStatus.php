<?php

namespace App\Enums;

enum IndicatorStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
        };
    }

    public function color(): string
    {
        return $this === self::Active ? 'green' : 'zinc';
    }
}
