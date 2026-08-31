<?php

use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

it('erases the line and the position it was holding up', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $transaction = Transaction::query()->where('user_id', $user->id)->sole();

    $this->from('/')
        ->delete("/transactions/{$transaction->id}")
        ->assertRedirect('/');

    /** Unique achat de l'enveloppe : la position n'a plus rien à projeter. */
    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        ->and(Holding::query()->where('asset_id', $instrument->id)->where('wallet_id', $wallet->id)->exists())
        ->toBeFalse();
});

it('keeps the position when something is left of it', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $extra = Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 5,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2026-02-01',
    ]);

    $this->delete("/transactions/{$extra->id}")->assertRedirect();

    expect((float) Holding::query()->where('asset_id', $instrument->id)->where('wallet_id', $wallet->id)->first()->quantity)
        ->toBe(10.0);
});

it('answers 404 on the line of another user and erases nothing', function () {
    portfolioFixture();
    ['user' => $stranger] = portfolioFixture(['name' => 'Globex', 'ticker' => 'GBX']);
    $theirs = Transaction::query()->where('user_id', $stranger->id)->sole();

    $this->delete("/transactions/{$theirs->id}")->assertNotFound();

    expect(Transaction::query()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('answers 404 on an unknown id', function () {
    portfolioFixture();

    $this->delete('/transactions/999999')->assertNotFound();
});
