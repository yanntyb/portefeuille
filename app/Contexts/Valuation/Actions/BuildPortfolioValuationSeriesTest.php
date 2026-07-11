<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;

it('builds a series from a user transactions and prices', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 120]);

    $series = app(BuildPortfolioValuationSeries::class)($user->id);

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('returns an empty series when the user has no transactions', function () {
    $user = User::factory()->create();

    $series = app(BuildPortfolioValuationSeries::class)($user->id);

    expect($series->labels)->toBe([])
        ->and($series->valuations)->toBe([]);
});
