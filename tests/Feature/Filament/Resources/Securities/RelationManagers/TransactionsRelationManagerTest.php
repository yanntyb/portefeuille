<?php

use App\Enums\TransactionType;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

it('stores sell transaction', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);

    // Buy 10 shares
    Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Buy,
        'quantity' => 10,
    ]);

    // Sell 5
    $sellTransaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Sell,
        'quantity' => 5,
    ]);

    expect((float) $sellTransaction->quantity)->toBe(5.0)
        ->and($sellTransaction->type)->toBe(TransactionType::Sell);
});

it('stores latest security price', function () {
    $security = Security::factory()->create();
    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 125.50,
        'date' => now(),
    ]);

    $price = SecurityPrice::query()
        ->where('security_id', $security->id)
        ->orderByDesc('date')
        ->value('close');

    expect((float) $price)->toBe(125.50);
});

it('stores transaction fees', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Buy,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 15.50,
    ]);

    expect((float) $transaction->fees)->toBe(15.50);
});

it('stores transaction notes', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id]);

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Buy,
        'quantity' => 10,
        'notes' => 'Achat lors de la baisse du marché',
    ]);

    expect($transaction->notes)->toBe('Achat lors de la baisse du marché');
});

it('stores broker info for cto transactions', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    $ctoWallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'wallet_id' => $ctoWallet->id,
        'type' => TransactionType::Buy,
        'quantity' => 5,
        'broker' => 'Interactive Brokers',
    ]);

    expect($transaction->broker)->toBe('Interactive Brokers');
});
