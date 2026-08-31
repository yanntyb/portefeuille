<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\CreateTransaction;
use App\Contexts\Portfolio\Datas\TransactionInputData;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;

it('records a buy and projects its position', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $transaction = app(CreateTransaction::class)(
        $user->id,
        inputData($wallet->id, $instrument->id),
    );

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->type)->toBe(TransactionType::Buy)
        ->and((float) $transaction->quantity)->toBe(5.0)
        ->and((float) $transaction->fees)->toBe(2.5)
        ->and($transaction->date->format('Y-m-d'))->toBe('2026-04-01');

    /** La fixture détenait 10 titres : la position suit l'achat sans qu'on la touche. */
    $holding = Holding::query()
        ->where('asset_id', $instrument->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $holding->quantity)->toBe(15.0);
});

it('takes the owner from its argument and never from the input', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $intruder = User::factory()->create();

    /**
     * `TransactionInputData` ne porte pas de `userId` : il n'y a pas de champ par lequel une
     * requête pourrait écrire dans le compte d'autrui. La garde est de construction.
     */
    $transaction = app(CreateTransaction::class)(
        $user->id,
        inputData($wallet->id, $instrument->id),
    );

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->user_id)->not->toBe($intruder->id);
});

it('lets the observer compute the realized gain of a sell', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    /** La fixture a acheté 10 titres à 80 le 1er janvier, sans frais. */
    $sell = app(CreateTransaction::class)(
        $user->id,
        inputData($wallet->id, $instrument->id, [
            'type' => 'sell',
            'quantity' => 4,
            'unitPrice' => 100,
            'fees' => 5,
        ]),
    );

    expect((float) $sell->realized_gain)->toBe(75.0); // (100 - 80) × 4 - 5
});

it('leaves the realized gain of a buy null', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $buy = app(CreateTransaction::class)($user->id, inputData($wallet->id, $instrument->id));

    /** Un achat ne réalise rien : la colonne reste nulle, elle ne devient pas zéro. */
    expect($buy->realized_gain)->toBeNull();
});

it('defaults the fees to zero when the form left them empty', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $transaction = app(CreateTransaction::class)(
        $user->id,
        TransactionInputData::fromValidated([
            'walletId' => $wallet->id,
            'assetId' => $instrument->id,
            'date' => '2026-04-01',
            'type' => 'buy',
            'quantity' => 5,
            'unitPrice' => 120,
        ]),
    );

    expect((float) $transaction->fees)->toBe(0.0);
});
