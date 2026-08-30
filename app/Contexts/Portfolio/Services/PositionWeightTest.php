<?php

use App\Contexts\Portfolio\Services\PositionWeight;

it('rapporte une position au total du portefeuille', function () {
    expect((new PositionWeight)->of(250.0, [250.0, 750.0]))->toBe(25.0);
});

it('exclut du total les positions sans cours connu', function () {
    expect((new PositionWeight)->of(250.0, [250.0, 250.0, null]))->toBe(50.0);
});

it('ne rend rien sans valeur de position', function () {
    expect((new PositionWeight)->of(null, [250.0, 750.0]))->toBeNull();
});

it('ne rend rien sur un portefeuille sans valeur', function () {
    expect((new PositionWeight)->of(250.0, []))->toBeNull();
});

it('rend cent pour cent sur une position unique', function () {
    expect((new PositionWeight)->of(250.0, [250.0]))->toBe(100.0);
});
