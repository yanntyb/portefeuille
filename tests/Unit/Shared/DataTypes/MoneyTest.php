<?php

use App\Shared\DataTypes\Money;

it('creates money with valid amount and currency', function () {
    $money = new Money(100, 'EUR');

    expect($money->amount)->toBe(100)
        ->and($money->currency)->toBe('EUR');
});

it('rejects negative amount', function () {
    expect(fn () => new Money(-10, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects zero amount', function () {
    expect(fn () => new Money(0, 'EUR'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects empty currency', function () {
    expect(fn () => new Money(100, ''))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects invalid currency code', function () {
    expect(fn () => new Money(100, 'INVALID'))
        ->toThrow(InvalidArgumentException::class);
});

it('checks equality for money with same values', function () {
    $money1 = new Money(100, 'EUR');
    $money2 = new Money(100, 'EUR');

    expect($money1->equals($money2))->toBeTrue();
});

it('checks inequality for money with different amounts', function () {
    $money1 = new Money(100, 'EUR');
    $money2 = new Money(200, 'EUR');

    expect($money1->equals($money2))->toBeFalse();
});

it('checks inequality for money with different currencies', function () {
    $money1 = new Money(100, 'EUR');
    $money2 = new Money(100, 'USD');

    expect($money1->equals($money2))->toBeFalse();
});

it('accepts valid currency codes', function () {
    $validCurrencies = ['EUR', 'USD', 'GBP', 'JPY'];

    foreach ($validCurrencies as $currency) {
        $money = new Money(100, $currency);
        expect($money->currency)->toBe($currency);
    }
});
