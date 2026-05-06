<?php

use App\Domains\Security\Enums\Sector;

it('has a label for every case', function (Sector $case) {
    expect($case->getLabel())->toBeString()->not->toBeEmpty();
})->with(Sector::cases());

it('has a color for every case', function (Sector $case) {
    expect($case->getColor())->toBeString()->toStartWith('rgb(');
})->with(Sector::cases());

it('has unique colors for all cases', function () {
    $colors = array_map(fn (Sector $case) => $case->getColor(), Sector::cases());

    expect($colors)->toHaveCount(count(array_unique($colors)));
});

it('can be instantiated from its value', function (Sector $case) {
    expect(Sector::from($case->value))->toBe($case);
})->with(Sector::cases());
