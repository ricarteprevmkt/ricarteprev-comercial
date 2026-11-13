<?php

namespace App\Support;

/**
 * CPF: validação dos dígitos, formatação e hash para checar duplicidade sem
 * guardar o número em claro (o número fica criptografado em outra coluna).
 */
final class Cpf
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $cpf = self::digits($value);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;

            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function format(?string $value): string
    {
        $cpf = self::digits($value);

        return strlen($cpf) === 11
            ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2)
            : $cpf;
    }

    /** Exibe só os 2 últimos dígitos: ***.***.***-12 */
    public static function masked(?string $value): string
    {
        $cpf = self::digits($value);

        return strlen($cpf) === 11 ? '***.***.***-'.substr($cpf, 9, 2) : '';
    }

    public static function hash(?string $value): ?string
    {
        $cpf = self::digits($value);

        return $cpf === '' ? null : hash_hmac('sha256', $cpf, (string) config('app.key'));
    }
}
