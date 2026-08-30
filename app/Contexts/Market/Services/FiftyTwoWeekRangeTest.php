<?php

use App\Contexts\Market\Services\FiftyTwoWeekRange;

it('rend le plus-haut, le plus-bas et la distance au plus-haut', function () {
    $range = (new FiftyTwoWeekRange)->of([80.0, 120.0, 90.0]);

    expect($range->high)->toBe(120.0)
        ->and($range->low)->toBe(80.0)
        ->and($range->gapPct)->toBe(-25.0);
});

it('rend une distance nulle quand le cours est sur son plus-haut', function () {
    expect((new FiftyTwoWeekRange)->of([80.0, 100.0, 120.0])->gapPct)->toBe(0.0);
});

it('ne regarde que les 252 dernières séances', function () {
    /** Un sommet ancien, hors fenêtre, ne doit pas peser sur la distance au plus-haut. */
    $closes = array_merge([1000.0], array_fill(0, FiftyTwoWeekRange::SESSIONS, 100.0));

    expect((new FiftyTwoWeekRange)->of($closes)->high)->toBe(100.0);
});

it('accepte une série plus courte que la fenêtre', function () {
    expect((new FiftyTwoWeekRange)->of([90.0, 100.0])->high)->toBe(100.0);
});

it('ne rend rien sur une série vide', function () {
    expect((new FiftyTwoWeekRange)->of([]))->toBeNull();
});
