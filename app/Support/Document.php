<?php

namespace App\Support;

use App\Enums\LeadType;

/**
 * CPF ou CNPJ de um contato: validação e hash para checar duplicidade sem
 * guardar o número em claro.
 */
final class Document
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value, LeadType $type): bool
    {
        return $type === LeadType::Person ? Cpf::isValid($value) : self::isValidCnpj($value);
    }

    public static function isValidCnpj(?string $value): bool
    {
        $cnpj = self::digits($value);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $weights = $length === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$i];
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;

            if ((int) $cnpj[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function format(?string $value): string
    {
        $d = self::digits($value);

        return match (strlen($d)) {
            11 => Cpf::format($d),
            14 => substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2),
            default => $d,
        };
    }

    public static function masked(?string $value): string
    {
        $d = self::digits($value);

        return match (strlen($d)) {
            11 => Cpf::masked($d),
            14 => '**.***.***/****-'.substr($d, 12, 2),
            default => '',
        };
    }

    public static function hash(?string $value): ?string
    {
        return Cpf::hash($value);
    }
}
