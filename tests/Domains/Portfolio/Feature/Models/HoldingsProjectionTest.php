<?php

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\HoldingsProjection;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

it('creates holdings projection on transaction created', function () {
    HoldingsProjection::query()->delete();
    Transaction::query()->forceDelete();

    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->pea()->create();
    $security = Security::factory()->create();

    // Auth as the user to avoid global scope issues
    $this->actingAs($user);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $projection = HoldingsProjection::query()->where('asset_id', $security->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect($projection)->not->toBeNull()
        ->and((float) $projection->quantity)->toBe(10.0)
        ->and((float) $projection->avg_cost)->toBe(100.0);
});

it('updates projection on additional transactions', function () {
    HoldingsProjection::query()->delete();

    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->pea()->create();
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 110,
    ]);

    $projection = HoldingsProjection::where('asset_id', $security->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect($projection)->not->toBeNull()
        ->and((float) $projection->quantity)->toBe(15.0)
        ->and((float) $projection->avg_cost)->toBe(103.333333, 2);
});
