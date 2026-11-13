<?php

namespace App\Enums;

enum CommissionType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Valor fixo (R$)',
            self::Percent => 'Percentual (%)',
        };
    }
}
