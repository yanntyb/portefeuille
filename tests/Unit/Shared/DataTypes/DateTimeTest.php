<?php

use App\Shared\DataTypes\DateTime;
use Carbon\Carbon;

it('creates datetime with valid carbon instance', function () {
    $carbon = Carbon::now();
    $dt = new DateTime($carbon);

    expect($dt->value)->toBe($carbon);
});

it('creates datetime with string', function () {
    $dt = new DateTime('2025-02-15 10:30:00');

    expect($dt->value)->toBeInstanceOf(Carbon::class);
    expect($dt->value->format('Y-m-d H:i:s'))->toBe('2025-02-15 10:30:00');
});

it('creates datetime with carbon now', function () {
    $dt = new DateTime('now');

    expect($dt->value)->toBeInstanceOf(Carbon::class);
});

it('checks equality for datetime with same values', function () {
    $carbon = Carbon::parse('2025-02-15 10:30:00');
    $dt1 = new DateTime($carbon);
    $dt2 = new DateTime(Carbon::parse('2025-02-15 10:30:00'));

    expect($dt1->equals($dt2))->toBeTrue();
});

it('checks inequality for datetime with different values', function () {
    $dt1 = new DateTime('2025-02-15 10:30:00');
    $dt2 = new DateTime('2025-02-16 10:30:00');

    expect($dt1->equals($dt2))->toBeFalse();
});

it('provides carbon instance directly', function () {
    $dt = new DateTime('2025-02-15');

    expect($dt->value->year)->toBe(2025)
        ->and($dt->value->month)->toBe(2)
        ->and($dt->value->day)->toBe(15);
});
