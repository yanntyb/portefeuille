<?php

use App\Domains\Asset\Enums\AssetType;

it('has all asset types defined', function () {
    expect(AssetType::Stock->value)->toBe('stock')
        ->and(AssetType::ETF->value)->toBe('etf')
        ->and(AssetType::Crypto->value)->toBe('crypto')
        ->and(AssetType::RealEstate->value)->toBe('real_estate')
        ->and(AssetType::Bond->value)->toBe('bond')
        ->and(AssetType::Savings->value)->toBe('savings');
});

it('has labels for all types', function () {
    expect(AssetType::Stock->getLabel())->toBe('Stock')
        ->and(AssetType::ETF->getLabel())->toBe('ETF')
        ->and(AssetType::Crypto->getLabel())->toBe('Cryptocurrency')
        ->and(AssetType::RealEstate->getLabel())->toBe('Real Estate')
        ->and(AssetType::Bond->getLabel())->toBe('Bond')
        ->and(AssetType::Savings->getLabel())->toBe('Savings Account');
});

it('has colors for all types', function () {
    foreach (AssetType::cases() as $type) {
        expect($type->getColor())->not->toBeNull();
    }
});
