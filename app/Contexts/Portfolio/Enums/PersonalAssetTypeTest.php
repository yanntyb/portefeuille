<?php

use App\Contexts\Portfolio\Enums\PersonalAssetType;

it('liste les valeurs', function () {
    expect(PersonalAssetType::values())->toBe(['real_estate', 'savings']);
});

it('fournit label, couleur et icône pour chaque cas', function (PersonalAssetType $type) {
    expect($type->getLabel())->toBeString()->not->toBeEmpty()
        ->and($type->getColor())->toBeString()->not->toBeEmpty()
        ->and($type->getIcon())->toStartWith('heroicon-');
})->with(PersonalAssetType::cases());
