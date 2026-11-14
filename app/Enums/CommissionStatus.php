<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Expected = 'expected';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'Prevista',
            self::Approved => 'Aprovada',
            self::Paid => 'Paga',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Expected => 'zinc',
            self::Approved => 'blue',
            self::Paid => 'green',
            self::Cancelled => 'red',
        };
    }
}
