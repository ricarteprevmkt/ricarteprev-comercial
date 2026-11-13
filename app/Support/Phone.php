<?php

namespace App\Support;

/**
 * Telefones brasileiros. Guardamos só dígitos com DDD (10 ou 11 dígitos),
 * sem o 55, para comparar contatos e montar links do WhatsApp.
 */
final class Phone
{
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $digits;
    }

    public static function isValid(?string $value): bool
    {
        $digits = self::normalize($value);

        return $digits !== null && in_array(strlen($digits), [10, 11], true);
    }

    public static function format(?string $value): string
    {
        $d = self::normalize($value) ?? '';

        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            default => $d,
        };
    }

    public static function whatsappUrl(?string $value): ?string
    {
        $d = self::normalize($value);

        return $d ? "https://wa.me/55{$d}" : null;
    }
}
