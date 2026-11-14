<?php

namespace App\Enums;

enum LeadType: string
{
    case Person = 'pf';
    case Company = 'pj';

    public function label(): string
    {
        return $this === self::Person ? 'Pessoa física' : 'Pessoa jurídica';
    }

    public function documentLabel(): string
    {
        return $this === self::Person ? 'CPF' : 'CNPJ';
    }
}
