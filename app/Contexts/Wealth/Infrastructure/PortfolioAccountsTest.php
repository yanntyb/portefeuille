<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Infrastructure\PortfolioAccounts;
use App\Contexts\Wealth\Ports\AccountsPort;

it('traduit les enveloppes du portefeuille sans rien recalculer', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $pea->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $accounts = app(AccountsPort::class)->accountsFor($user->id);

    expect($accounts)->toHaveCount(1)
        ->and($accounts[0]->walletName)->toBe('PEA')
        ->and($accounts[0]->accountTypeLabel)->toBe('PEA')
        ->and($accounts[0]->marketValue)->toBe(1000.0)
        ->and($accounts[0]->gainPct)->toBe(25.0);
});

it('ne rend aucune enveloppe pour un utilisateur inconnu', function () {
    expect(app(AccountsPort::class)->accountsFor(9999))->toBe([]);
});

it('porte le compte espèces de chaque enveloppe', function () {
    $user = User::factory()->create();

    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);
    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->withdrawal()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-02-01', 'amount' => 300,
    ]);

    // Une position sans aucun mouvement d'espèces : le cas dégénéré, à 0 et non à vide.
    $cto = Wallet::factory()->for($user)->create(['name' => 'CTO']);
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $cto->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);

    // Aucune position, seulement des espèces : le cas que la nouvelle règle rend possible.
    $cashOnly = Wallet::factory()->for($user)->create(['name' => 'Livret']);
    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $cashOnly->id, 'date' => '2026-01-01', 'amount' => 5000,
    ]);

    $accounts = collect(app(PortfolioAccounts::class)->accountsFor($user->id))->keyBy('walletName');

    expect($accounts['PEA']->cashBalance)->toBe(700.0)
        ->and($accounts['CTO']->cashBalance)->toBe(0.0)
        ->and($accounts['Livret']->cashBalance)->toBe(5000.0)
        ->and($accounts['Livret']->marketValue)->toBe(0.0);
});
