<?php

use App\Shared\DataTypes\UserId;

it('creates user id with positive integer', function () {
    $id = new UserId(456);

    expect($id->value)->toBe(456);
});

it('rejects zero', function () {
    expect(fn () => new UserId(0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects negative integer', function () {
    expect(fn () => new UserId(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('checks equality for user ids with same value', function () {
    $id1 = new UserId(456);
    $id2 = new UserId(456);

    expect($id1->equals($id2))->toBeTrue();
});

it('checks inequality for user ids with different values', function () {
    $id1 = new UserId(456);
    $id2 = new UserId(789);

    expect($id1->equals($id2))->toBeFalse();
});
