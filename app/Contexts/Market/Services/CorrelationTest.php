<?php

use App\Contexts\Market\Services\Correlation;

/**
 * Une série de clôtures datées, longue d'assez de séances pour que la corrélation se calcule.
 *
 * @param  list<float>  $pattern  Motif répété jusqu'à la longueur demandée.
 * @return array<string, float>
 */
function correlationSeries(array $pattern, int $length = 60): array
{
    $day = new DateTimeImmutable('2024-01-01');
    $closes = [];

    for ($index = 0; $index < $length; $index++) {
        $closes[$day->format('Y-m-d')] = $pattern[$index % count($pattern)];
        $day = $day->modify('+1 day');
    }

    return $closes;
}

it('rend une corrélation parfaite entre deux séries qui bougent ensemble', function () {
    $matrix = (new Correlation)->matrix([
        7 => correlationSeries([100.0, 110.0, 105.0, 120.0]),
        9 => correlationSeries([50.0, 55.0, 52.5, 60.0]),
    ]);

    expect($matrix->keys)->toBe([7, 9])
        ->and(round($matrix->rows[0][1], 6))->toBe(1.0);
});

it('rend une corrélation opposée entre deux séries qui bougent en sens inverse', function () {
    $monte = correlationSeries([100.0, 110.0, 105.0, 120.0]);

    /** Chaque séance, la seconde série encaisse l'exact opposé du rendement de la première. */
    $baisse = [];
    $precedent = null;
    $cours = 100.0;

    foreach ($monte as $date => $close) {
        if ($precedent !== null) {
            $cours *= 1 - ($close / $precedent - 1);
        }

        $baisse[$date] = $cours;
        $precedent = $close;
    }

    expect(round((new Correlation)->matrix([7 => $monte, 9 => $baisse])->rows[0][1], 6))
        ->toBe(-1.0);
});

it('pose une corrélation parfaite sur la diagonale', function () {
    $matrix = (new Correlation)->matrix([
        7 => correlationSeries([100.0, 110.0, 105.0]),
        9 => correlationSeries([50.0, 40.0, 60.0]),
    ]);

    expect($matrix->rows[0][0])->toBe(1.0)
        ->and($matrix->rows[1][1])->toBe(1.0);
});

it('rend la même corrélation dans les deux sens', function () {
    $matrix = (new Correlation)->matrix([
        7 => correlationSeries([100.0, 110.0, 105.0]),
        9 => correlationSeries([50.0, 40.0, 60.0]),
    ]);

    expect($matrix->rows[0][1])->toBe($matrix->rows[1][0]);
});

it('ne corrèle pas deux séries qui partagent trop peu de séances', function () {
    /** Une paire trop courte donnerait un chiffre que le hasard suffit à expliquer. */
    $court = Correlation::MIN_RETURNS;

    $matrix = (new Correlation)->matrix([
        7 => correlationSeries([100.0, 110.0, 105.0], $court),
        9 => correlationSeries([50.0, 55.0, 52.5], $court),
    ]);

    expect($matrix->rows[0][1])->toBeNull();
});

it('ne corrèle pas une série plate, qui ne varie jamais', function () {
    $matrix = (new Correlation)->matrix([
        7 => correlationSeries([100.0]),
        9 => correlationSeries([50.0, 55.0, 52.5]),
    ]);

    expect($matrix->rows[0][1])->toBeNull();
});

it('ne compare que les séances communes aux deux séries', function () {
    $complete = correlationSeries([100.0, 110.0, 105.0, 120.0]);
    /** La seconde série ne cote qu'à partir du onzième jour, et vaut la moitié de la première. */
    $tardive = array_map(
        fn (float $close): float => $close / 2,
        array_slice($complete, 10, preserve_keys: true),
    );

    $matrix = (new Correlation)->matrix([7 => $complete, 9 => $tardive]);

    expect(round($matrix->rows[0][1], 6))->toBe(1.0);
});

it('rend une matrice d’un seul point sur un instrument seul', function () {
    $matrix = (new Correlation)->matrix([7 => correlationSeries([100.0, 110.0])]);

    expect($matrix->keys)->toBe([7])
        ->and($matrix->rows)->toBe([[1.0]]);
});

it('ne rend aucune matrice sans instrument', function () {
    $matrix = (new Correlation)->matrix([]);

    expect($matrix->keys)->toBe([])
        ->and($matrix->rows)->toBe([]);
});
