<?php

use App\Contexts\Market\Enums\InstrumentType;

it('liste les valeurs dans l’ordre', function () {
    expect(InstrumentType::values())->toBe(['stock', 'etf', 'crypto', 'bond']);
});

it('fournit un label non vide pour chaque cas', function (InstrumentType $type) {
    expect($type->getLabel())->toBeString()->not->toBeEmpty();
})->with(InstrumentType::cases());

it('fournit une couleur non vide pour chaque cas', function (InstrumentType $type) {
    expect($type->getColor())->toBeString()->not->toBeEmpty();
})->with(InstrumentType::cases());

it('fournit une icône heroicon pour chaque cas', function (InstrumentType $type) {
    expect($type->getIcon())->toStartWith('heroicon-');
})->with(InstrumentType::cases());
