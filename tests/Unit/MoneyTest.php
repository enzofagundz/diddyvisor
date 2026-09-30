<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parser_rejects_negative_exponential_thousand_separator_and_overflow(): void
    {
        foreach (['-1', '1e3', '1.000,00', '1,001', '92233720368547758,08', '', 'NaN'] as $value) {
            try {
                Money::parse($value);
                $this->fail('Valor inválido aceito: '.$value);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_integer_boundary_and_aggregate_strings_preserve_every_digit(): void
    {
        $this->assertSame(PHP_INT_MAX, Money::parse('92233720368547758,07'));
        $this->assertSame('R$ 184.467.440.737.095.516,14', Money::display('18446744073709551614'));
        $this->assertSame(10, Money::parse('0,1'));
    }

    public function test_decimal_input_preserves_exact_cents(): void
    {
        $this->assertSame(10001, Money::parse('100,01'));
        $this->assertSame('100,01', Money::decimal(10001));
    }
}
