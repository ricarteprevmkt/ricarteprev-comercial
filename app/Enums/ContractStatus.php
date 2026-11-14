<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando conclusão',
            self::Signed => 'Assinado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Signed => 'green',
            self::Cancelled => 'zinc',
        };
    }
}
