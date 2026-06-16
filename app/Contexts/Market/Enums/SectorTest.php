<?php

use App\Contexts\Market\Enums\Sector;

it('a douze secteurs', function () {
    expect(Sector::cases())->toHaveCount(12);
});

it('résout un secteur depuis sa valeur', function () {
    expect(Sector::from('technology'))->toBe(Sector::Technology);
});

it('fournit un label français non vide pour chaque cas', function (Sector $sector) {
    expect($sector->getLabel())->toBeString()->not->toBeEmpty();
})->with(Sector::cases());

it('fournit une couleur rgb pour chaque cas', function (Sector $sector) {
    expect($sector->getColor())->toStartWith('rgb(');
})->with(Sector::cases());
