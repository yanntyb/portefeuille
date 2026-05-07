<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

it('transaction can be created with wallet and security', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'date' => now(),
        'quantity' => 10,
        'unit_price' => 150.50,
        'type' => 'buy',
    ]);

    $transaction = Transaction::where('wallet_id', $wallet->id)
        ->where('security_id', $security->id)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->wallet_id)->toBe($wallet->id)
        ->and($transaction->security_id)->toBe($security->id)
        ->and($transaction->user_id)->toBe($user->id);
});

it('stores quantity and unit price correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);

    Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
        'date' => now(),
        'quantity' => 25,
        'unit_price' => 200.75,
        'type' => 'buy',
    ]);

    $transaction = Transaction::where('security_id', $security->id)->first();

    expect((float) $transaction->quantity)->toBe(25.0)
        ->and((float) $transaction->unit_price)->toBe(200.75);
});
