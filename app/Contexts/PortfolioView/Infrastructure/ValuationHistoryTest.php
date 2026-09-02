<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->valuation = app(ValuationPort::class);
});

it('rend les performances glissantes d\'une exposition', function () {
    ['user' => $user] = portfolioFixture();

    $performances = $this->valuation->performancesFor($user->id, HoldingScope::ofClasses([AssetClass::Equity]));

    expect($performances)->not->toBeEmpty();
    expect($performances[0]->key)->toBeString()
        ->and($performances[0]->label)->toBeString()
        ->and($performances[0]->pct)->toBeFloat();
});

it('rend des performances vides pour un utilisateur sans transaction', function () {
    expect($this->valuation->performancesFor(999, HoldingScope::ofClasses([AssetClass::Equity])))->toBe([]);
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

/**
 * Le pas hebdomadaire ne retient qu'un point par semaine ISO : une position ouverte en début de
 * semaine y tombait à un point unique, qu'ECharts peint sans ligne. Sous un trimestre
 * d'historique, la série garde donc son pas quotidien.
 */
it('garde le pas quotidien sur une position ouverte dans la semaine', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'Compte-titres']);
    $instrument = Instrument::factory()->create();

    foreach (['2026-08-31' => 2123.2466, '2026-09-01' => 2134.03] as $date => $close) {
        Price::factory()->create(['asset_id' => $instrument->id, 'date' => $date, 'close' => $close]);
    }

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 0.02343151,
        'unit_price' => 2125.76,
        'date' => '2026-08-31',
    ]);

    $series = $this->valuation->assetSeriesFor($user->id, $instrument->id);

    expect($series->labels)->toBe(['2026-08-31', '2026-09-01'])
        ->and($series->valuations)->toHaveCount(2);
});

it('repasse au pas hebdomadaire au-delà du trimestre', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'Compte-titres']);
    $instrument = Instrument::factory()->create();

    $start = Carbon::parse('2026-01-05');

    foreach (range(0, 129) as $offset) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => $start->copy()->addDays($offset)->format('Y-m-d'),
            'close' => 100 + $offset,
        ]);
    }

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2026-01-05',
    ]);

    $series = $this->valuation->assetSeriesFor($user->id, $instrument->id);

    $weeks = array_map(
        fn (string $label): string => Carbon::parse($label)->format('o-W'),
        $series->labels,
    );

    expect($series->labels)->toHaveCount(19)
        ->and($weeks)->toBe(array_values(array_unique($weeks)));
});
