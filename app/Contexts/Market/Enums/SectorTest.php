<?php

use App\Contexts\Market\Enums\Sector;

it('has twelve sectors', function () {
    expect(Sector::cases())->toHaveCount(12);
});

it('resolves a sector from its value', function () {
    expect(Sector::from('technology'))->toBe(Sector::Technology);
});

it('provides a non-empty label for each case', function (Sector $sector) {
    expect($sector->getLabel())->toBeString()->not->toBeEmpty();
})->with(Sector::cases());

it('provides an rgb color for each case', function (Sector $sector) {
    expect($sector->getColor())->toStartWith('rgb(');
})->with(Sector::cases());
