<?php

use App\Contexts\RealEstate\Services\SeriesStepper;
use Illuminate\Support\Carbon;

it('pose tous les lundis puis aujourd\'hui', function () {
    $labels = (new SeriesStepper)->weeklyLabels(
        Carbon::parse('2026-01-15'),
        Carbon::parse('2026-02-04 15:30:00'),
    );

    expect($labels[0])->toBe('2026-01-12')
        ->and(end($labels))->toBe('2026-02-04');
});

it('ne duplique pas le dernier label quand aujourd\'hui est un lundi', function () {
    $labels = (new SeriesStepper)->weeklyLabels(
        Carbon::parse('2026-01-15'),
        Carbon::parse('2026-02-02 15:30:00'),
    );

    expect(array_count_values($labels)['2026-02-02'])->toBe(1);
});

it('rend la dernière valeur estimée de date antérieure ou égale', function () {
    $points = [['2026-01-01', 100.0], ['2026-06-01', 150.0]];

    expect((new SeriesStepper)->valueAt($points, '2026-03-01'))->toBe(100.0)
        ->and((new SeriesStepper)->valueAt($points, '2026-06-01'))->toBe(150.0);
});

it('rend zéro avant la première estimation', function () {
    expect((new SeriesStepper)->valueAt([['2026-01-01', 100.0]], '2025-12-31'))->toBe(0.0);
});

it('cumule les montants jusqu\'au label inclus', function () {
    expect((new SeriesStepper)->sumUpTo(['2026-01' => 100.0, '2026-06' => 50.0], '2026-03-01'))->toBe(100.0);
});
