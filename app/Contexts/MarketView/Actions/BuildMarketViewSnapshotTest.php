<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('porte la page liste et une fiche par position détenue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['classes']['equity'])->toHaveKeys([
        'overview', 'trends', 'performances', 'evolutionSeries', 'sectorBreakdown', 'income', 'annualIncome',
    ])
        ->and($snapshot['assets'])->toHaveKey($instrument->id)
        ->and($snapshot['assets'][$instrument->id])->toHaveKeys([
            'instrument', 'performances', 'priceHistory', 'valuation', 'dividends',
        ]);
});

it('range la crypto à part, sans dividendes sur ses fiches', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['assets'])->toHaveKey($bitcoin->id)
        ->and($snapshot['assets'][$bitcoin->id])->not->toHaveKey('dividends');
});

it('carries one list per exposure and every held asset once', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 80.0,
    ]);

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect(array_keys($snapshot['classes']))->toBe(AssetClass::values())
        ->and($snapshot['assets'])->toHaveKey($gold->id);
});

it('withholds sectors and income from the exposures that have none', function () {
    $user = User::factory()->create();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['classes']['equity'])->toHaveKeys(['sectorBreakdown', 'income', 'annualIncome'])
        ->and($snapshot['classes']['crypto'])->not->toHaveKey('sectorBreakdown')
        ->and($snapshot['classes']['crypto'])->not->toHaveKey('income');
});

/**
 * Fige la composition servie quand la base n'a aucun utilisateur : c'est elle que garantissait
 * `emptyClasses()`, et les ports doivent la reproduire sans ce chemin dédié.
 */
it('rend les mêmes listes vides sans aucun utilisateur', function () {
    $snapshot = app(BuildMarketViewSnapshot::class)(0);

    expect($snapshot['assets'])->toBe([]);
    expect(array_keys($snapshot['classes']))
        ->toBe(array_map(fn (AssetClass $class): string => $class->value, AssetClass::cases()));

    $equity = $snapshot['classes']['equity'];

    expect(array_keys($equity))->toBe([
        'overview', 'trends', 'performances', 'evolutionSeries', 'sectorBreakdown', 'income', 'annualIncome',
    ]);
    expect($equity['overview']->jsonSerialize())
        ->toBe(['totalValue' => 0.0, 'totalCost' => 0.0, 'totalGain' => 0.0, 'totalGainPct' => 0.0, 'holdings' => []]);
    expect($equity['trends'])->toBe([])
        ->and($equity['performances'])->toBe([])
        ->and($equity['sectorBreakdown'])->toBe([])
        ->and($equity['annualIncome'])->toBe([]);
    expect($equity['evolutionSeries']->jsonSerialize())->toBe(['labels' => [], 'perAsset' => []]);
    expect($equity['income']->jsonSerialize())->toBe([
        'totalReceived' => 0.0, 'last12Months' => 0.0, 'estimatedAnnual' => 0.0, 'bySource' => [],
    ]);

    expect(array_keys($snapshot['classes']['crypto']))
        ->toBe(['overview', 'trends', 'performances', 'evolutionSeries']);
});
