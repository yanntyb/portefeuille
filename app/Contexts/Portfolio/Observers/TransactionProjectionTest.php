<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create();
    $this->asset = Instrument::factory()->create();
});

function makeTx(object $ctx, string $type, float $qty, float $price, array $extra = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'user_id' => $ctx->user->id,
        'wallet_id' => $ctx->wallet->id,
        'asset_id' => $ctx->asset->id,
        'type' => $type,
        'quantity' => $qty,
        'unit_price' => $price,
    ], $extra));
}

it('projects a holding when a buy is recorded', function () {
    makeTx($this, 'buy', 10, 80);

    $holding = Holding::query()->where('asset_id', $this->asset->id)->where('wallet_id', $this->wallet->id)->first();
    expect((float) $holding->quantity)->toBe(10.0)
        ->and((float) $holding->avg_cost)->toBe(80.0);
});

it('reduces the projection and stores realized gain on a sell', function () {
    makeTx($this, 'buy', 10, 80, ['date' => now()->subMonth()]);
    $sell = makeTx($this, 'sell', 4, 100, ['fees' => 5, 'date' => now()]);

    $holding = Holding::query()->where('asset_id', $this->asset->id)->where('wallet_id', $this->wallet->id)->first();
    expect((float) $holding->quantity)->toBe(6.0)
        ->and((float) $sell->fresh()->realized_gain)->toBe(75.0); // (100-80)*4 - 5
});

it('deletes the projection when a delete empties the position', function () {
    $buy = makeTx($this, 'buy', 10, 80);
    expect(Holding::query()->where('asset_id', $this->asset->id)->exists())->toBeTrue();

    $buy->delete();

    expect(Holding::query()->where('asset_id', $this->asset->id)->exists())->toBeFalse();
});

it('recomputes the realized gain of a later sell when its buy is corrected', function () {
    $buy = makeTx($this, 'buy', 10, 100, ['fees' => 0, 'date' => '2026-01-01']);
    $sell = makeTx($this, 'sell', 5, 150, ['fees' => 0, 'date' => '2026-03-01']);

    expect((float) $sell->fresh()->realized_gain)->toBe(250.0);

    $buy->update(['unit_price' => 120]);

    /** Le gain de la vente suit le prix de revient : c'est une autre ligne que celle qui a bougé. */
    expect((float) $sell->fresh()->realized_gain)->toBe(150.0);
});

it('recomputes the realized gain of a sell when one of its buys is deleted', function () {
    $first = makeTx($this, 'buy', 10, 100, ['fees' => 0, 'date' => '2026-01-01']);
    makeTx($this, 'buy', 10, 140, ['fees' => 0, 'date' => '2026-01-15']);
    $sell = makeTx($this, 'sell', 5, 150, ['fees' => 0, 'date' => '2026-03-01']);

    expect((float) $sell->fresh()->realized_gain)->toBe(150.0); // PRU 120

    $first->delete();

    expect((float) $sell->fresh()->realized_gain)->toBe(50.0); // PRU 140
});

it('reprojects the old and new wallet when a transaction moves', function () {
    $otherWallet = Wallet::factory()->for($this->user)->create();
    $buy = makeTx($this, 'buy', 10, 80);

    $buy->update(['wallet_id' => $otherWallet->id]);

    expect(Holding::query()->where('wallet_id', $this->wallet->id)->exists())->toBeFalse()
        ->and(Holding::query()->where('wallet_id', $otherWallet->id)->where('asset_id', $this->asset->id)->exists())->toBeTrue();
});
