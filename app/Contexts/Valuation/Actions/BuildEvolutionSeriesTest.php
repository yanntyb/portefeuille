<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;

it('builds the merged evolution series with named per-asset invested', function () {
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
        'quantity' => 5, 'unit_price' => 50, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $apple->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $amazon->id, 'date' => '2026-01-01', 'close' => 50]);

    $data = app(BuildEvolutionSeries::class)($user->id, ValuationRange::Max, ValuationGranularity::Day);

    expect($data->labels)->toBe(['2026-01-01']);

    $byName = collect($data->perAsset)->keyBy('name');
    expect($byName)->toHaveKeys(['Apple', 'Amazon'])
        ->and($byName['Apple']->value)->toBe([1000.0])
        ->and($byName['Apple']->invested)->toBe([1000.0])
        ->and($byName['Amazon']->value)->toBe([250.0])
        ->and($byName['Amazon']->invested)->toBe([250.0]);
});

it('returns an empty evolution series when the user has no transactions', function () {
    $user = User::factory()->create();

    expect(app(BuildEvolutionSeries::class)($user->id)->perAsset)->toBe([]);
});
