<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;

it('declares its cases in the order the wealth summary reads them', function () {
    expect(AssetClass::values())->toBe(['equity', 'bond', 'commodity', 'crypto']);
});

it('labels every exposure in French', function () {
    expect(AssetClass::Equity->getLabel())->toBe('Actions')
        ->and(AssetClass::Bond->getLabel())->toBe('Obligations')
        ->and(AssetClass::Commodity->getLabel())->toBe('Matières premières')
        ->and(AssetClass::Crypto->getLabel())->toBe('Crypto');
});

it('slugs every exposure in French', function () {
    expect(AssetClass::Equity->slug())->toBe('actions')
        ->and(AssetClass::Bond->slug())->toBe('obligations')
        ->and(AssetClass::Commodity->slug())->toBe('matieres-premieres')
        ->and(AssetClass::Crypto->slug())->toBe('crypto');
});

it('defaults an ETF to equity, and every other wrapper to its own exposure', function () {
    expect(AssetClass::defaultForType(InstrumentType::Stock))->toBe(AssetClass::Equity)
        ->and(AssetClass::defaultForType(InstrumentType::ETF))->toBe(AssetClass::Equity)
        ->and(AssetClass::defaultForType(InstrumentType::Bond))->toBe(AssetClass::Bond)
        ->and(AssetClass::defaultForType(InstrumentType::Commodity))->toBe(AssetClass::Commodity)
        ->and(AssetClass::defaultForType(InstrumentType::Crypto))->toBe(AssetClass::Crypto);
});

it('gives every exposure a distinct colour token', function () {
    $tokens = array_map(fn (AssetClass $class): string => $class->getColor(), AssetClass::cases());

    expect($tokens)->toHaveCount(count(array_unique($tokens)));
});

it('carries sectors on equity alone', function () {
    expect(AssetClass::Equity->hasSectors())->toBeTrue()
        ->and(AssetClass::Bond->hasSectors())->toBeFalse()
        ->and(AssetClass::Commodity->hasSectors())->toBeFalse()
        ->and(AssetClass::Crypto->hasSectors())->toBeFalse();
});
