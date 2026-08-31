<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\CreateTransaction;
use App\Contexts\Portfolio\Actions\UpdateTransaction;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('corrects a quantity and reprojects the position', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $transaction = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
        'quantity' => 5,
    ]));

    app(UpdateTransaction::class)($transaction, inputData($wallet->id, $instrument->id, [
        'quantity' => 8,
    ]));

    /** 10 titres de la fixture, plus 8 au lieu de 5. */
    $holding = Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $transaction->fresh()->quantity)->toBe(8.0)
        ->and((float) $holding->quantity)->toBe(18.0);
});

it('reprojects both wallets when the line moves', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $elsewhere = Wallet::factory()->for($user)->create(['name' => 'Ailleurs']);

    $transaction = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
        'quantity' => 5,
    ]));

    app(UpdateTransaction::class)($transaction, inputData($elsewhere->id, $instrument->id, [
        'quantity' => 5,
    ]));

    $origin = Holding::query()->where('wallet_id', $wallet->id)->where('asset_id', $instrument->id)->first();
    $destination = Holding::query()->where('wallet_id', $elsewhere->id)->where('asset_id', $instrument->id)->first();

    /** L'ancienne enveloppe retombe aux 10 titres de la fixture, la nouvelle en gagne 5. */
    expect((float) $origin->quantity)->toBe(10.0)
        ->and((float) $destination->quantity)->toBe(5.0);
});

it('keeps the owner out of reach', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $intruder = User::factory()->create();

    $transaction = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id));

    app(UpdateTransaction::class)($transaction, inputData($wallet->id, $instrument->id, [
        'quantity' => 9,
    ]));

    /** On corrige une opération, on ne la donne pas : `user_id` n'est pas dans la liste écrite. */
    expect($transaction->fresh()->user_id)->toBe($user->id)
        ->and($transaction->fresh()->user_id)->not->toBe($intruder->id);
});

it('recomputes the realized gain of the sells that leaned on a corrected buy', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $buy = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id, [
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

    /** 10 titres à 80 (fixture) et 10 à 120 : prix de revient 100. */
    expect((float) $sell->realized_gain)->toBe(250.0);

    app(UpdateTransaction::class)($buy, inputData($wallet->id, $instrument->id, [
        'date' => '2026-01-02',
        'quantity' => 10,
        'unitPrice' => 160,
        'fees' => 0,
    ]));

    /** Prix de revient porté à 120 : le gain de la vente suit, sans qu'elle ait bougé. */
    expect((float) $sell->fresh()->realized_gain)->toBe(150.0);
});
