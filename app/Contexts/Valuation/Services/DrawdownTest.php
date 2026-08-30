<?php

use App\Contexts\Valuation\Datas\DrawdownData;
use App\Contexts\Valuation\Services\Drawdown;

it('mesure la chute la plus profonde depuis un plus-haut', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'],
        [1000.0, 1200.0, 900.0, 1100.0],
    );

    expect($drawdown->maxDepth)->toBe(25.0)
        ->and($drawdown->peakLabel)->toBe('2026-02-01')
        ->and($drawdown->troughLabel)->toBe('2026-03-01');
});

it('mesure la chute en cours depuis le dernier plus-haut', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 2000.0, 1500.0],
    );

    expect($drawdown->currentDepth)->toBe(25.0);
});

it('ne rend aucune chute en cours quand le plus-haut est le dernier point', function () {
    $drawdown = (new Drawdown)->of(['2026-01-01', '2026-02-01'], [1000.0, 1200.0]);

    expect($drawdown->currentDepth)->toBe(0.0)
        ->and($drawdown->maxDepth)->toBe(0.0);
});

it('ne rend aucune date sur une série qui ne baisse jamais', function () {
    $drawdown = (new Drawdown)->of(['2026-01-01', '2026-02-01'], [1000.0, 1200.0]);

    expect($drawdown->peakLabel)->toBeNull()
        ->and($drawdown->troughLabel)->toBeNull();
});

it('prend le premier point comme plus-haut d\'une série décroissante', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 800.0, 500.0],
    );

    expect($drawdown->maxDepth)->toBe(50.0)
        ->and($drawdown->peakLabel)->toBe('2026-01-01')
        ->and($drawdown->troughLabel)->toBe('2026-03-01')
        ->and($drawdown->currentDepth)->toBe(50.0);
});

it('ne mesure rien sur une série vide', function () {
    expect((new Drawdown)->of([], []))->toEqual(DrawdownData::empty());
});

it('ne mesure rien tant que la valeur reste nulle ou négative', function () {
    expect((new Drawdown)->of(['2026-01-01'], [0.0]))->toEqual(DrawdownData::empty());
});

it('mesure la perte en cours depuis le plus-haut historique, non depuis un sommet intermédiaire', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'],
        [1000.0, 400.0, 900.0, 800.0],
    );

    expect($drawdown->maxDepth)->toBe(60.0)
        ->and($drawdown->peakLabel)->toBe('2026-01-01')
        ->and($drawdown->troughLabel)->toBe('2026-02-01')
        ->and($drawdown->currentDepth)->toBe(20.0);
});

it('lève une exception si labels et values ont des longueurs différentes', function () {
    expect(fn () => (new Drawdown)->of(['a'], [1000.0, 800.0, 500.0]))
        ->toThrow(InvalidArgumentException::class, 'labels et values doivent avoir la même longueur');
});
