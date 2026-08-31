<?php

use App\Contexts\Portfolio\Actions\CreateTransaction;
use App\Contexts\Portfolio\Actions\DeleteTransaction;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

it('erases the line and reprojects what remains', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $transaction = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
        'quantity' => 5,
    ]));

    app(DeleteTransaction::class)($transaction);

    $holding = Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        /** Retour aux 10 titres de la fixture. */
        ->and((float) $holding->quantity)->toBe(10.0);
});

it('deletes the position when nothing is left of it', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    /** L'unique achat de la fixture : l'effacer vide la position. */
    $only = Transaction::query()->where('user_id', $user->id)->sole();

    app(DeleteTransaction::class)($only);

    expect(Holding::query()->where('asset_id', $instrument->id)->where('wallet_id', $wallet->id)->exists())
        ->toBeFalse();
});

it('gives the later sells back the cost basis of the buys that remain', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $extra = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
        'date' => '2026-01-02',
        'quantity' => 10,
        'unitPrice' => 120,
        'fees' => 0,
    ]));

    $sell = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
        'date' => '2026-03-01',
        'type' => 'sell',
        'quantity' => 5,
        'unitPrice' => 150,
        'fees' => 0,
    ]));

    expect((float) $sell->realized_gain)->toBe(250.0); // prix de revient 100

    app(DeleteTransaction::class)($extra);

    /** Ne restent que les 10 titres à 80 de la fixture : prix de revient 80. */
    expect((float) $sell->fresh()->realized_gain)->toBe(350.0);
});
