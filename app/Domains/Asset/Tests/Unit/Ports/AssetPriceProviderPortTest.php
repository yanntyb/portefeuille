<?php

use App\Domains\Asset\Ports\AssetPriceProviderPort;

it('defines provider contract', function () {
    $reflection = new ReflectionClass(AssetPriceProviderPort::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->getMethods())->toHaveCount(3)
        ->and($reflection->hasMethod('getCurrentPrice'))->toBeTrue()
        ->and($reflection->hasMethod('getPriceHistory'))->toBeTrue()
        ->and($reflection->hasMethod('supports'))->toBeTrue();
});
