<?php

namespace App\Enums;

/** Evento que dá direito à comissão. */
enum CommissionTrigger: string
{
    case ContractSigned = 'contract_signed';
    case RevenueReceived = 'revenue_received';

    public function label(): string
    {
        return match ($this) {
            self::ContractSigned => 'Na assinatura do contrato',
            self::RevenueReceived => 'No recebimento da receita',
        };
    }
}
