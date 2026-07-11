<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('casts attributes and links wallet and user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    $transaction = Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 5,
        'unit_price' => 100,
    ]);

    expect($transaction->type)->toBe(TransactionType::Sell)
        ->and((float) $transaction->quantity)->toBe(5.0)
        ->and($transaction->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($transaction->wallet->is($wallet))->toBeTrue()
        ->and($transaction->user->is($user))->toBeTrue();
});

it('defaults to a buy', function () {
    expect(Transaction::factory()->make()->type)->toBe(TransactionType::Buy);
});
