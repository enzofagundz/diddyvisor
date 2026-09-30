<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function parse(string $value): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(?:,([0-9]{1,2}))?$/D', trim($value), $matches)) {
            throw new InvalidArgumentException('Informe um valor como 100,00, sem separador de milhar.');
        }

        $digits = ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0') ?: '0';
        $limit = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new InvalidArgumentException('Valor acima do limite permitido.');
        }

        return (int) $digits;
    }

    public static function decimal(int|string $cents): string
    {
        $digits = str_pad((string) $cents, 3, '0', STR_PAD_LEFT);

        return substr($digits, 0, -2).','.substr($digits, -2);
    }

    public static function display(int|string $cents): string
    {
        [$whole, $fraction] = explode(',', self::decimal($cents));

        return 'R$ '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }
}
