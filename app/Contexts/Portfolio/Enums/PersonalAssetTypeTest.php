<?php

use App\Contexts\Portfolio\Enums\PersonalAssetType;

it('lists the values', function () {
    expect(PersonalAssetType::values())->toBe(['real_estate', 'savings']);
});

it('provides label, color and icon for each case', function (PersonalAssetType $type) {
    expect($type->getLabel())->toBeString()->not->toBeEmpty()
        ->and($type->getColor())->toBeString()->not->toBeEmpty()
        ->and($type->getIcon())->toStartWith('heroicon-');
})->with(PersonalAssetType::cases());
