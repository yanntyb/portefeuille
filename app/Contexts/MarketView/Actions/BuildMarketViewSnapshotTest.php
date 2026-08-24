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
