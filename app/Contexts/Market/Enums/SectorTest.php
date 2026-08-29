<?php

use App\Contexts\Market\Enums\Sector;

it('provides a non-empty label for each case', function (Sector $sector) {
    expect($sector->getLabel())->toBeString()->not->toBeEmpty();
})->with(Sector::cases());

it('provides an rgb color for each case', function (Sector $sector) {
    expect($sector->getColor())->toStartWith('rgb(');
})->with(Sector::cases());
