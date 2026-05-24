<?php

use App\Shared\DataTypes\Percentage;

it('creates percentage with valid value', function () {
    $percent = new Percentage(50);

    expect($percent->value)->toBe(50);
});

it('accepts zero', function () {
    $percent = new Percentage(0);

    expect($percent->value)->toBe(0);
});

it('accepts 100', function () {
    $percent = new Percentage(100);

    expect($percent->value)->toBe(100);
});

it('accepts decimal values', function () {
    $percent = new Percentage(33.5);

    expect($percent->value)->toBe(33.5);
});

it('rejects negative values', function () {
    expect(fn () => new Percentage(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects values above 100', function () {
    expect(fn () => new Percentage(101))
        ->toThrow(InvalidArgumentException::class);
});

it('checks equality for percentages with same values', function () {
    $percent1 = new Percentage(50);
    $percent2 = new Percentage(50);

    expect($percent1->equals($percent2))->toBeTrue();
});

it('checks inequality for percentages with different values', function () {
    $percent1 = new Percentage(50);
    $percent2 = new Percentage(75);

    expect($percent1->equals($percent2))->toBeFalse();
});
