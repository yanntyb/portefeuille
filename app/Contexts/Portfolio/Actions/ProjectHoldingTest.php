<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

function seedTx(User $user, Wallet $wallet, Instrument $asset, string $type, float $qty, float $price): void
{
    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'type' => $type,
        'quantity' => $qty,
        'unit_price' => $price,
    ]);
}

it('projects quantity and weighted average cost from buys and sells', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    seedTx($user, $wallet, $asset, 'buy', 10, 100);   // cost 1000
    seedTx($user, $wallet, $asset, 'buy', 10, 140);   // cost 1400
    seedTx($user, $wallet, $asset, 'sell', 5, 200);   // reduces qty only

    app(ProjectHolding::class)($user->id, $asset->id, $wallet->id);

    $holding = Holding::query()->where('asset_id', $asset->id)->where('wallet_id', $wallet->id)->first();
    expect((float) $holding->quantity)->toBe(15.0)          // 20 bought - 5 sold
        ->and((float) $holding->avg_cost)->toBe(120.0);     // 2400 / 20
});

it('removes the holding when everything is sold', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    seedTx($user, $wallet, $asset, 'buy', 10, 100);
    seedTx($user, $wallet, $asset, 'sell', 10, 120);

    app(ProjectHolding::class)($user->id, $asset->id, $wallet->id);

    expect(Holding::query()->where('asset_id', $asset->id)->where('wallet_id', $wallet->id)->exists())->toBeFalse();
});
