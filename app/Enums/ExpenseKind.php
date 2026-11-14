<?php

namespace App\Enums;

/** Efetiva (gasto realizado, com comprovante) ou estimada (previsão). */
enum ExpenseKind: string
{
    case Actual = 'actual';
    case Estimated = 'estimated';

    public function label(): string
    {
        return $this === self::Actual ? 'Efetiva' : 'Estimada';
    }
}
