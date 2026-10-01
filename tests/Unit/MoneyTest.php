<?php

use App\Support\Money;

test('parser rejects negative, exponential, thousand separator and overflow', function () {
    foreach (['-1', '1e3', '1.000,00', '1,001', '92233720368547758,08', '', 'NaN'] as $value) {
        expect(fn () => Money::parse($value))->toThrow(InvalidArgumentException::class);
    }
});

test('integer boundary and aggregate strings preserve every digit', function () {
    expect(Money::parse('92233720368547758,07'))->toBe(PHP_INT_MAX)
        ->and(Money::display('18446744073709551614'))->toBe('R$ 184.467.440.737.095.516,14')
        ->and(Money::parse('0,1'))->toBe(10);
});

test('decimal input preserves exact cents', function () {
    expect(Money::parse('100,01'))->toBe(10001)
        ->and(Money::decimal(10001))->toBe('100,01');
});
