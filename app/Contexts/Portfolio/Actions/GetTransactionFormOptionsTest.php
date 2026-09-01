<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetTransactionFormOptions;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('annonce le solde espèces de chaque enveloppe', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create(['name' => 'Compte-titres']);
    $pea = Wallet::factory()->for($user)->pea()->create(['name' => 'PEA']);

    Transaction::factory()->for($user)->deposit()->create([
        'wallet_id' => $cto->id,
        'date' => '2026-01-05',
        'amount' => 1000,
    ]);
    Transaction::factory()->for($user)->withdrawal()->create([
        'wallet_id' => $cto->id,
        'date' => '2026-02-05',
        'amount' => 300,
    ]);
    Transaction::factory()->for($user)->deposit()->create([
        'wallet_id' => $pea->id,
        'date' => '2026-01-05',
        'amount' => 500,
    ]);

    $options = app(GetTransactionFormOptions::class)($user->id);

    $balances = collect($options->wallets)->keyBy('id');

    expect($balances[$cto->id]->cashBalance)->toBe(700.0)
        ->and($balances[$pea->id]->cashBalance)->toBe(500.0);
});

it('annonce un solde nul pour une enveloppe sans mouvement', function () {
    $user = User::factory()->create();
    Wallet::factory()->for($user)->cto()->create(['name' => 'Compte-titres']);

    $options = app(GetTransactionFormOptions::class)($user->id);

    /** Zéro, et non `null` : le compte existe, il ne porte rien — c'est un plafond de retrait. */
    expect($options->wallets[0]->cashBalance)->toBe(0.0);
});

it('ne compte que les mouvements de l\'enveloppe, jamais ceux du voisin', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create(['name' => 'Compte-titres']);
    Wallet::factory()->for($user)->pea()->create(['name' => 'PEA']);

    Transaction::factory()->for($user)->deposit()->create([
        'wallet_id' => $cto->id,
        'date' => '2026-01-05',
        'amount' => 1000,
    ]);

    $options = app(GetTransactionFormOptions::class)($user->id);

    $balances = collect($options->wallets)->keyBy('id');

    expect($balances[$cto->id]->cashBalance)->toBe(1000.0);
});

it('sert le solde dans le JSON du formulaire', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create(['name' => 'Compte-titres']);

    Transaction::factory()->for($user)->deposit()->create([
        'wallet_id' => $cto->id,
        'date' => '2026-01-05',
        'amount' => 1000,
    ]);

    $json = json_decode(json_encode(app(GetTransactionFormOptions::class)($user->id)), true);

    /** `toEqual` : JSON écrit un flottant rond sans décimale, il revient en entier. */
    expect($json['wallets'][0]['cashBalance'])->toEqual(1000.0);
});
