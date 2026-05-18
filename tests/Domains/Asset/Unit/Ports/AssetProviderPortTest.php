<?php

use App\Domains\Asset\Ports\AssetProviderPort;

it('defines provider contract', function () {
    $reflection = new ReflectionClass(AssetProviderPort::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->getMethods())->toHaveCount(2)
        ->and($reflection->hasMethod('findBySymbol'))->toBeTrue()
        ->and($reflection->hasMethod('supports'))->toBeTrue();
});
