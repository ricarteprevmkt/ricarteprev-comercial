<?php

namespace App\Enums;

/** Sobre qual valor o percentual é aplicado. */
enum CommissionBase: string
{
    case ContractValue = 'contract_value';
    case ExpectedRevenue = 'expected_revenue';
    case ReceivedRevenue = 'received_revenue';

    public function label(): string
    {
        return match ($this) {
            self::ContractValue => 'Valor do contrato',
            self::ExpectedRevenue => 'Receita prevista',
            self::ReceivedRevenue => 'Receita recebida',
        };
    }
}
