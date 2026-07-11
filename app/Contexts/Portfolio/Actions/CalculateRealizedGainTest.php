<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('returns null for a buy', function () {
    $buy = Transaction::factory()->buy()->make();

    expect(app(CalculateRealizedGain::class)($buy))->toBeNull();
});

it('computes realized gain against the average cost of prior buys', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => now()->subMonths(2),
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 140, 'date' => now()->subMonth(),
    ]);

    // PRU = (1000 + 1400) / 20 = 120
    $sell = Transaction::factory()->sell()->make([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 5, 'unit_price' => 200, 'fees' => 10, 'date' => now(),
    ]);

    // (200 - 120) * 5 - 10 = 390
    expect(app(CalculateRealizedGain::class)($sell))->toBe(390.0);
});

it('ignores buys made after the sell date', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => now()->subMonths(2),
    ]);
    Transaction::factory()->buy()->create([   // after the sell — must be excluded
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 300, 'date' => now(),
    ]);

    $sell = Transaction::factory()->sell()->make([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 5, 'unit_price' => 200, 'fees' => 0, 'date' => now()->subMonth(),
    ]);

    // PRU from prior buy only = 100 → (200 - 100) * 5 - 0 = 500
    expect(app(CalculateRealizedGain::class)($sell))->toBe(500.0);
});
