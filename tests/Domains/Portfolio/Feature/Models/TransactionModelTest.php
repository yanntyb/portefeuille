<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

it('belongs to a security', function () {
    $security = Security::factory()->create();
    $transaction = Transaction::factory()->pea()->create(['security_id' => $security->id]);

    expect($transaction->security->id)->toBe($security->id);
});

it('casts date to Carbon instance', function () {
    $transaction = Transaction::factory()->pea()->create(['date' => '2024-06-15']);

    expect($transaction->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($transaction->date->format('Y-m-d'))->toBe('2024-06-15');
});

it('belongs to a wallet', function () {
    $wallet = Wallet::factory()->pea()->create();
    $transaction = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    expect($transaction->wallet->id)->toBe($wallet->id)
        ->and($transaction->wallet->name)->toBe('PEA');
});

it('casts decimal fields correctly', function () {
    $transaction = Transaction::factory()->pea()->create([
        'quantity' => 10.5,
        'unit_price' => 99.1234,
        'fees' => 5.50,
    ]);

    $transaction->refresh();

    expect($transaction->quantity)->toBe('10.5000')
        ->and($transaction->unit_price)->toBe('99.1234')
        ->and($transaction->fees)->toBe('5.50');
});

it('filters transactions by authenticated user via global scope', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user);

    Transaction::factory()->pea()->create(['user_id' => auth()->id()]);
    Transaction::factory()->pea()->create(['user_id' => auth()->id()]);

    $otherWallet = Wallet::factory()->pea()->create(['user_id' => $otherUser->id]);
    Transaction::factory()->create(['user_id' => $otherUser->id, 'wallet_id' => $otherWallet->id]);

    expect(Transaction::query()->count())->toBe(2);
});

it('does not apply global scope when no user is authenticated', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user);

    Transaction::factory()->pea()->create(['user_id' => auth()->id()]);

    $otherWallet = Wallet::factory()->pea()->create(['user_id' => $otherUser->id]);
    Transaction::factory()->create(['user_id' => $otherUser->id, 'wallet_id' => $otherWallet->id]);

    auth()->logout();

    expect(Transaction::query()->count())->toBe(2);
});
