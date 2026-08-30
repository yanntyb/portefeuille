<?php

use App\Contexts\Market\Services\RelativeStrengthIndex;

it('rend le RSI d\'une série sans lissage à faire', function () {
    /** Variations +1 puis −0,5 : gains moyens 0,5, pertes moyennes 0,25, RS = 2. */
    expect(round((new RelativeStrengthIndex)->of([10.0, 11.0, 10.5], 2), 4))
        ->toBe(round(100 - 100 / 3, 4));
});

it('lisse à la Wilder et non en moyenne simple', function () {
    /**
     * Gains [1, 0, 1], pertes [0, 0,5, 0]. Wilder : gains 0,75, pertes 0,125, RS = 6, RSI ≈ 85,71.
     * Une moyenne simple des deux dernières variations donnerait RS = 2 et RSI ≈ 66,67.
     */
    expect(round((new RelativeStrengthIndex)->of([10.0, 11.0, 10.5, 11.5], 2), 4))
        ->toBe(round(100 - 100 / 7, 4));
});

it('rend 100 sur une série qui ne baisse jamais', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 11.0, 12.0], 2))->toBe(100.0);
});

it('rend 0 sur une série qui ne monte jamais', function () {
    expect((new RelativeStrengthIndex)->of([12.0, 11.0, 10.0], 2))->toBe(0.0);
});

it('rend 100 sur une série plate, faute de baisse à mesurer', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 10.0, 10.0], 2))->toBe(100.0);
});

it('ne rend rien sans assez de variations pour remplir la fenêtre', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 11.0], 2))->toBeNull();
});
