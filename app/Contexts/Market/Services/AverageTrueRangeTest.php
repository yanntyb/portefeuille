<?php

use App\Contexts\Market\Datas\TrueRangeBar;
use App\Contexts\Market\Services\AverageTrueRange;

/** @param  list<array{float, float, float}>  $rows */
function trueRangeBars(array $rows): array
{
    return array_map(
        fn (array $row): TrueRangeBar => new TrueRangeBar(high: $row[0], low: $row[1], close: $row[2]),
        $rows,
    );
}

it('moyenne les amplitudes vraies de la fenêtre', function () {
    /** Amplitudes : 4 (10 → 106-102) puis 3 (105-102), moyenne 3,5 sur une fenêtre de 2. */
    $atr = (new AverageTrueRange)->of(trueRangeBars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 103.0],
    ]), 2);

    expect($atr->value)->toBe(3.5);
});

it('compte l\'écart à la clôture de la veille dans l\'amplitude', function () {
    /** Trou baissier : la barre s'ouvre et se ferme sous la clôture de la veille. */
    $atr = (new AverageTrueRange)->of(trueRangeBars([
        [104.0, 100.0, 104.0],
        [99.0, 97.0, 98.0],
        [99.0, 97.0, 98.0],
    ]), 2);

    /** Amplitudes : max(2, |99-104|, |97-104|) = 7, puis max(2, 1, 1) = 2. Moyenne 4,5. */
    expect($atr->value)->toBe(4.5);
});

it('lisse à la Wilder au-delà de la première fenêtre', function () {
    $atr = (new AverageTrueRange)->of(trueRangeBars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 103.0],
        [113.0, 103.0, 110.0],
    ]), 2);

    /** Première moyenne 3,5 ; dernière amplitude 10 ; Wilder : (3,5 × 1 + 10) / 2 = 6,75. */
    expect($atr->value)->toBe(6.75);
});

it('exprime l\'amplitude en pourcentage du dernier cours', function () {
    $atr = (new AverageTrueRange)->of(trueRangeBars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 100.0],
    ]), 2);

    expect($atr->percent)->toBe(3.5);
});

it('ne rend rien avec moins de barres que la fenêtre plus une', function () {
    expect((new AverageTrueRange)->of(trueRangeBars([[104.0, 100.0, 102.0], [106.0, 102.0, 105.0]]), 2))
        ->toBeNull();
});

it('rend une amplitude nulle sur une série parfaitement plate', function () {
    $atr = (new AverageTrueRange)->of(trueRangeBars([
        [100.0, 100.0, 100.0],
        [100.0, 100.0, 100.0],
        [100.0, 100.0, 100.0],
    ]), 2);

    expect($atr->value)->toBe(0.0)
        ->and($atr->percent)->toBe(0.0);
});
