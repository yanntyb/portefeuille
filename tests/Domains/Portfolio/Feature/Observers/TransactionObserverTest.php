<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

it('sets realized_gain to null for buy transactions', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $transaction = Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    expect($transaction->realized_gain)->toBeNull();
});

it('calculates realized_gain for sell transactions', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $sellTransaction = Transaction::factory()->sell()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 120,
        'fees' => 10,
    ]);

    // realized_gain = (120 - 100) * 5 - 10 = 100 - 10 = 90
    expect((float) $sellTransaction->realized_gain)->toBe(90.0);
});

it('calculates pru with multiple buy transactions', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 110,
    ]);

    $sellTransaction = Transaction::factory()->sell()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 3,
        'unit_price' => 120,
        'fees' => 0,
    ]);

    // PRU = (10*100 + 5*110) / 15 = 1550 / 15 = 103.33
    // realized_gain = (120 - 103.33) * 3 ≈ 50
    expect((float) $sellTransaction->realized_gain)->toBeGreaterThanOrEqual(50);
});

it('handles sell with no prior buy transactions', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $sellTransaction = Transaction::factory()->sell()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 120,
        'fees' => 10,
    ]);

    // PRU = 0, so realized_gain = (120 - 0) * 5 - 10 = 590
    expect((float) $sellTransaction->realized_gain)->toBe(590.0);
});

it('recalculates realized_gain on update', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    $sellTransaction = Transaction::factory()->sell()->create([
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 120,
        'fees' => 10,
    ]);

    $oldGain = $sellTransaction->realized_gain;

    $sellTransaction->update(['unit_price' => 130]);

    // realized_gain = (130 - 100) * 5 - 10 = 140
    expect((float) $sellTransaction->fresh()->realized_gain)->toBe(140.0)
        ->and((float) $sellTransaction->fresh()->realized_gain)->not->toBe((float) $oldGain);
});

it('filters by wallet when calculating pru', function () {
    $security = Security::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);
    $peaWallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);
    $ctoWallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $peaWallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
    ]);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'wallet_id' => $ctoWallet->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 110,
    ]);

    $peaSellTransaction = Transaction::factory()->sell()->create([
        'security_id' => $security->id,
        'wallet_id' => $peaWallet->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 120,
        'fees' => 0,
    ]);

    // only PEA buy is considered, PRU = 100
    // realized_gain = (120 - 100) * 5 = 100
    expect((float) $peaSellTransaction->realized_gain)->toBe(100.0);
});
