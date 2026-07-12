<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;

it('builds the five position performances anchored on the last price day', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-06-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $performances = app(BuildAssetPerformances::class)($user->id, $asset->id);

    expect($performances)->toHaveCount(5)
        ->and(array_map(fn ($perf) => $perf->key, $performances))->toBe(['1M', '3M', '6M', '1Y', '2Y'])
        ->and(array_map(fn ($perf) => $perf->label, $performances))->toBe(['1 mois', '3 mois', '6 mois', '1 an', '2 ans'])
        ->and($performances[0]->pct)->toBe(20.0)
        ->and($performances[3]->pct)->toBeNull()
        ->and($performances[4]->pct)->toBeNull();
});

it('returns no performances when the user has no transaction for the asset', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();

    expect(app(BuildAssetPerformances::class)($user->id, $asset->id))->toBe([]);
});
