<?php

use App\Contexts\Market\Enums\InstrumentType;

it('lists the values in order', function () {
    expect(InstrumentType::values())->toBe(['stock', 'etf', 'crypto', 'bond', 'commodity']);
});

it('provides a non-empty label for each case', function (InstrumentType $type) {
    expect($type->getLabel())->toBeString()->not->toBeEmpty();
})->with(InstrumentType::cases());

it('provides a non-empty color for each case', function (InstrumentType $type) {
    expect($type->getColor())->toBeString()->not->toBeEmpty();
})->with(InstrumentType::cases());

it('provides a heroicon icon for each case', function (InstrumentType $type) {
    expect($type->getIcon())->toStartWith('heroicon-');
})->with(InstrumentType::cases());
