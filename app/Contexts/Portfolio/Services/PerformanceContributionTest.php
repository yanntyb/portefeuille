<?php

use App\Contexts\Portfolio\Services\PerformanceContribution;

function contributionPosition(int $id, string $name, ?float $gain, ?float $marketValue): array
{
    return ['assetId' => $id, 'assetName' => $name, 'gain' => $gain, 'marketValue' => $marketValue];
}

it('rapporte le gain d\'une position à la valeur totale, pas à son propre coût', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Petite ligne', 80.0, 200.0),
        contributionPosition(2, 'Grosse ligne', 360.0, 3000.0),
    ], 10000.0);

    expect($lines[0]->assetName)->toBe('Grosse ligne')
        ->and($lines[0]->contribution)->toBe(3.6)
        ->and($lines[0]->weight)->toBe(30.0)
        ->and($lines[1]->contribution)->toBe(0.8)
        ->and($lines[1]->weight)->toBe(2.0);
});

it('trie par contribution décroissante', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Faible', 100.0, 1000.0),
        contributionPosition(2, 'Forte', 900.0, 1000.0),
    ], 10000.0);

    expect(array_map(fn ($line): string => $line->assetName, $lines))->toBe(['Forte', 'Faible']);
});

it('garde les contributions négatives d\'un portefeuille en perte', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Perdante', -500.0, 1000.0),
    ], 10000.0);

    expect($lines[0]->contribution)->toBe(-5.0);
});

it('exclut les positions dont le gain est inconnu', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Connue', 100.0, 1000.0),
        contributionPosition(2, 'Inconnue', null, 1000.0),
    ], 10000.0);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->assetName)->toBe('Connue');
});

it('ne définit ni contribution ni poids sur une valeur totale nulle', function () {
    $lines = (new PerformanceContribution)->of([contributionPosition(1, 'Ligne', 100.0, 1000.0)], 0.0);

    expect($lines[0]->contribution)->toBeNull()
        ->and($lines[0]->weight)->toBeNull();
});

it('ne définit aucun poids pour une position sans valeur de marché', function () {
    $lines = (new PerformanceContribution)->of([contributionPosition(1, 'Ligne', 100.0, null)], 10000.0);

    expect($lines[0]->contribution)->toBe(1.0)
        ->and($lines[0]->weight)->toBeNull();
});

it('rend une liste vide sans aucune position', function () {
    expect((new PerformanceContribution)->of([], 10000.0))->toBe([]);
});
