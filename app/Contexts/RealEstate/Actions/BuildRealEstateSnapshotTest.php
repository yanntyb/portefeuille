<?php

use App\Contexts\RealEstate\Actions\BuildRealEstateSnapshot;

it('porte la page liste et une fiche par bien', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $snapshot = app(BuildRealEstateSnapshot::class)($user->id);

    expect($snapshot['list'])->toHaveKeys(['realEstate', 'series', 'profitability', 'income'])
        ->and($snapshot['byId'])->toHaveKey($property->id)
        ->and($snapshot['byId'][$property->id])->toHaveKeys(['property', 'amortization']);
});
