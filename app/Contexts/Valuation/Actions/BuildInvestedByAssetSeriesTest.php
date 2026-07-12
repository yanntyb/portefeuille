<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildInvestedByAssetSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;

it('builds one named invested series per title', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $apple = Instrument::factory()->create(['name' => 'Apple']);
    $amazon = Instrument::factory()->create(['name' => 'Amazon']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $apple->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $amazon->id,
        'quantity' => 5, 'unit_price' => 50, 'date' => '2026-02-01',
    ]);

    $data = app(BuildInvestedByAssetSeries::class)($user->id);

    expect($data->labels)->toBe(['2026-01-01', '2026-02-01']);
    $byName = collect($data->series)->keyBy('name');
    expect($byName)->toHaveKeys(['Apple', 'Amazon']);
    expect($byName['Apple']->invested)->toBe([1000.0, 1000.0]);
    expect($byName['Amazon']->invested)->toBe([0.0, 250.0]);
});

it('returns an empty series when the user has no transactions', function () {
    $user = User::factory()->create();

    expect(app(BuildInvestedByAssetSeries::class)($user->id)->series)->toBe([]);
});

it('windows the invested-by-asset series by range', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $apple = Instrument::factory()->create(['name' => 'Apple']);
    $amazon = Instrument::factory()->create(['name' => 'Amazon']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $apple->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $amazon->id,
        'quantity' => 5, 'unit_price' => 50, 'date' => '2026-03-01',
    ]);

    // Dernier label 2026-03-01, range 1M => cutoff 2026-02-01 : seul 2026-03-01 reste.
    $data = app(BuildInvestedByAssetSeries::class)($user->id, ValuationRange::OneMonth, ValuationGranularity::Month);

    expect($data->labels)->toBe(['2026-03-01']);
    $byName = collect($data->series)->keyBy('name');
    expect($byName['Apple']->invested)->toBe([1000.0])
        ->and($byName['Amazon']->invested)->toBe([250.0]);
});
