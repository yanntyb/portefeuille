<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

function makeHolding(User $user, InstrumentType $type, float $close, float $qty, float $avgCost): Instrument
{
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $qty,
        'avg_cost' => $avgCost,
    ]);

    return $asset;
}

it('computes value, cost and gain for a single holding', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->totalCost)->toBe(800.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->totalGainPct)->toBe(25.0)
        ->and($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->marketValue)->toBe(1000.0)
        ->and($overview->holdings[0]->gainPct)->toBe(25.0);
});

it('excludes a holding without a known price from the totals', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80); // valued
    $wallet = Wallet::factory()->for($user)->create();
    $noPrice = Instrument::factory()->ofType(InstrumentType::Bond)->create();
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $noPrice->id,
        'quantity' => 5,
        'avg_cost' => 90,
    ]);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->holdings)->toHaveCount(2);

    $bondLine = collect($overview->holdings)->firstWhere('type', InstrumentType::Bond);
    expect($bondLine->lastPrice)->toBeNull()
        ->and($bondLine->marketValue)->toBeNull();
});

it('returns an empty overview when the user has no holdings', function () {
    $user = User::factory()->create();

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([]);
});

it('keeps market value but excludes gain when avg_cost is unknown', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80); // value 1000, cost 800

    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 4,
        'avg_cost' => null,
    ]);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1200.0)
        ->and($overview->totalCost)->toBe(800.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->totalGainPct)->toBe(25.0);

    $etfLine = collect($overview->holdings)->firstWhere('type', InstrumentType::ETF);
    expect($etfLine->marketValue)->toBe(200.0)
        ->and($etfLine->gain)->toBeNull()
        ->and($etfLine->gainPct)->toBeNull();
});

it('keeps only the holdings of the requested classes', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 6, avgCost: 50);   // 600
    makeHolding($user, InstrumentType::Crypto, close: 100, qty: 4, avgCost: 50);  // 400

    $securities = app(GetPortfolioOverview::class)($user, [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity]);
    $crypto = app(GetPortfolioOverview::class)($user, [AssetClass::Crypto]);

    expect($securities->totalValue)->toBe(600.0)
        ->and($securities->holdings)->toHaveCount(1)
        ->and($securities->holdings[0]->type)->toBe(InstrumentType::Stock)
        ->and($crypto->totalValue)->toBe(400.0)
        ->and($crypto->holdings)->toHaveCount(1)
        ->and($crypto->holdings[0]->type)->toBe(InstrumentType::Crypto);
});

it('keeps only the holdings of the exposures it is given', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Price::factory()->create(['asset_id' => $stock->id, 'close' => 50.0]);

    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 2, 'avg_cost' => 80.0,
    ]);
    Holding::factory()->create([
        'asset_id' => $stock->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 4, 'avg_cost' => 40.0,
    ]);

    $overview = app(GetPortfolioOverview::class)($user, [AssetClass::Commodity]);

    expect($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->assetId)->toBe($gold->id)
        ->and($overview->holdings[0]->assetClass)->toBe(AssetClass::Commodity)
        ->and($overview->totalValue)->toBe(200.0);
});
