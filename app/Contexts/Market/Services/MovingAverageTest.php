<?php

use App\Contexts\Market\Services\MovingAverage;

it('moyenne les dernières clôtures de la fenêtre', function () {
    expect((new MovingAverage)->of([10.0, 20.0, 30.0, 40.0], 2))->toBe(35.0);
});

it('ignore les clôtures antérieures à la fenêtre', function () {
    expect((new MovingAverage)->of([1.0, 1.0, 10.0, 20.0, 30.0], 3))->toBe(20.0);
});

it('ne rend rien quand la série est plus courte que la fenêtre', function () {
    expect((new MovingAverage)->of([10.0, 20.0], 3))->toBeNull();
});

it('ne rend rien sur une fenêtre nulle ou négative', function () {
    expect((new MovingAverage)->of([10.0, 20.0], 0))->toBeNull();
});
