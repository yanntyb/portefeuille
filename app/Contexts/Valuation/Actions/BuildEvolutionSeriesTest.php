<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;

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

    $data = app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Day);

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

it('keeps only the assets of the requested classes, without rebuilding the series', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $apple = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'Apple']);
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);

    foreach ([$apple, $bitcoin] as $asset) {
        Transaction::factory()->buy()->create([
            'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
            'quantity' => 1, 'unit_price' => 100, 'date' => '2026-01-01',
        ]);
        Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    }

    $securities = app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Day, [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity]);
    $crypto = app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Day, [AssetClass::Crypto]);

    // Mêmes labels de part et d'autre : le filtre trie les actifs, il ne rejoue pas la grille.
    expect(collect($securities->perAsset)->pluck('name')->all())->toBe(['Apple'])
        ->and(collect($crypto->perAsset)->pluck('name')->all())->toBe(['Bitcoin'])
        ->and($crypto->labels)->toBe($securities->labels);
});
