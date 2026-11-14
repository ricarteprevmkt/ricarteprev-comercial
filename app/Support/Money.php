<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Contas com dinheiro sem passar por float (bcmath, 2 casas).
 */
final class Money
{
    /** @return numeric-string */
    public static function of(mixed $value): string
    {
        $value = $value === null || $value === '' ? '0' : (string) $value;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$value}");
        }

        return bcadd($value, '0', 2);
    }

    /** @return numeric-string */
    public static function sub(mixed $a, mixed $b): string
    {
        return bcsub(self::of($a), self::of($b), 2);
    }

    /** Percentual de um valor: $base × $percent / 100, arredondado ao centavo. @return numeric-string */
    public static function percent(mixed $base, mixed $percent): string
    {
        return self::round(bcdiv(bcmul(self::of($base), self::of($percent), 6), '100', 6));
    }

    /**
     * Arredonda para 2 casas, metade para longe do zero (bcmath só trunca).
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    public static function round(string $value): string
    {
        $half = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $half, 6), '0', 2);
    }

    /** -1, 0 ou 1. */
    public static function compare(mixed $a, mixed $b): int
    {
        return bccomp(self::of($a), self::of($b), 2);
    }

    public static function format(mixed $value): string
    {
        return 'R$ '.number_format((float) self::of($value), 2, ',', '.');
    }
}
