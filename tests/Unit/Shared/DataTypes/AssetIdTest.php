<?php

use App\Shared\DataTypes\AssetId;

it('creates asset id with positive integer', function () {
    $id = new AssetId(123);

    expect($id->value)->toBe(123);
});

it('rejects zero', function () {
    expect(fn () => new AssetId(0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects negative integer', function () {
    expect(fn () => new AssetId(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('checks equality for asset ids with same value', function () {
    $id1 = new AssetId(123);
    $id2 = new AssetId(123);

    expect($id1->equals($id2))->toBeTrue();
});

it('checks inequality for asset ids with different values', function () {
    $id1 = new AssetId(123);
    $id2 = new AssetId(456);

    expect($id1->equals($id2))->toBeFalse();
});
