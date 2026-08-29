<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Ports\ValuationPort;

beforeEach(function () {
    $this->valuation = app(ValuationPort::class);
});

it('rend les performances glissantes d\'une exposition', function () {
    ['user' => $user] = portfolioFixture();

    $performances = $this->valuation->performancesFor($user->id, AssetClass::Equity);

    expect($performances)->not->toBeEmpty();
    expect($performances[0]->key)->toBeString()
        ->and($performances[0]->label)->toBeString()
        ->and($performances[0]->pct)->toBeFloat();
});

it('rend des performances vides pour un utilisateur sans transaction', function () {
    expect($this->valuation->performancesFor(999, AssetClass::Equity))->toBe([]);
});

/**
 * `BuildEvolutionSeries` filtre APRÈS son cache : la grille d'abscisses reste celle du portefeuille
 * entier, seule la liste par actif se réduit à l'exposition demandée. L'adaptateur est un pur
 * remappage et ne doit surtout pas refiltrer.
 */
it('ne garde que les actifs de l\'exposition, sur l\'abscisse de tout le portefeuille', function () {
    ['user' => $user] = cryptoFixture();

    $equity = $this->valuation->evolutionFor($user->id, AssetClass::Equity);
    $crypto = $this->valuation->evolutionFor($user->id, AssetClass::Crypto);

    expect($equity->labels)->toBe($crypto->labels);
    expect(collect($equity->perAsset)->pluck('name'))->toContain('ACME');
    expect(collect($crypto->perAsset)->pluck('name'))->not->toContain('ACME');
});

it('rend les clés que le graphe attend', function () {
    ['user' => $user] = portfolioFixture();

    $series = $this->valuation->evolutionFor($user->id, AssetClass::Equity);

    expect(array_keys($series->jsonSerialize()))->toBe(['labels', 'perAsset']);
    expect(array_keys($series->perAsset[0]->jsonSerialize()))
        ->toBe(['assetId', 'name', 'value', 'invested']);
});

it('rend une évolution vide pour un utilisateur sans transaction', function () {
    $series = $this->valuation->evolutionFor(999, AssetClass::Equity);

    expect($series->labels)->toBe([])
        ->and($series->perAsset)->toBe([]);
});

it('rend les performances d\'un actif seul', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $performances = $this->valuation->assetPerformancesFor($user->id, $instrument->id);

    expect($performances)->not->toBeEmpty()
        ->and($performances[0]->key)->toBeString();
});

it('rend la valorisation d\'un actif seul, cours compris', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $series = $this->valuation->assetSeriesFor($user->id, $instrument->id);

    expect(array_keys($series->jsonSerialize()))
        ->toBe(['labels', 'valuations', 'invested', 'prices']);
    expect($series->labels)->not->toBeEmpty()
        ->and($series->valuations)->toHaveCount(count($series->labels));
});

it('rend une valorisation vide pour un actif jamais acheté', function () {
    ['user' => $user] = portfolioFixture();

    $series = $this->valuation->assetSeriesFor($user->id, 999);

    expect($series->labels)->toBe([])
        ->and($series->prices)->toBe([]);
});
