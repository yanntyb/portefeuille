<?php

use App\Domains\Asset\Ports\AssetSectorProviderPort;

it('defines provider contract', function () {
    $reflection = new ReflectionClass(AssetSectorProviderPort::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->getMethods())->toHaveCount(2)
        ->and($reflection->hasMethod('getSectorAllocations'))->toBeTrue()
        ->and($reflection->hasMethod('supports'))->toBeTrue();
});
