<?php

use App\Contexts\MarketView\Services\SparklineReducer;

it('calcule la variation entre le premier et le dernier cours', function () {
    expect((new SparklineReducer)->changePct([100.0, 120.0, 110.0]))->toBe(10.0);
});

it('ne calcule aucune variation sur moins de deux points', function () {
    expect((new SparklineReducer)->changePct([100.0]))->toBeNull()
        ->and((new SparklineReducer)->changePct([]))->toBeNull();
});

it('ne calcule aucune variation depuis un cours nul', function () {
    expect((new SparklineReducer)->changePct([0.0, 120.0]))->toBeNull();
});

it('rend la série telle quelle quand elle tient sous la limite', function () {
    expect((new SparklineReducer)->downsample([1.0, 2.0, 3.0], 24))->toBe([1.0, 2.0, 3.0]);
});

it('sous-échantillonne en gardant les deux extrémités', function () {
    $points = (new SparklineReducer)->downsample(range(1.0, 100.0), 5);

    expect($points)->toHaveCount(5)
        ->and($points[0])->toBe(1.0)
        ->and($points[4])->toBe(100.0);
});
