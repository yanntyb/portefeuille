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

it('rend l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $account = app(PortfolioAccounts::class)->accountFor($user->id, $wallet->id);

    expect($account?->walletId)->toBe($wallet->id)
        ->and($account?->marketValue)->toBe(1000.0);
});

it('ne rend rien pour une enveloppe inconnue ou tenue par un autre', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    $accounts = app(PortfolioAccounts::class);

    expect($accounts->accountFor($user->id, 999999))->toBeNull()
        ->and($accounts->accountFor($user->id, $foreign->id))->toBeNull();
});

it('ne rend que les positions de l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 400]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 300,
    ]);

    $positions = app(PortfolioAccounts::class)->positionsFor($user->id, $wallet->id);

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id)
        ->and($positions[0]->walletId)->toBe($wallet->id);
});

it('ventile l\'enveloppe par classe d\'actif, la plus grosse part en tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 250]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 250,
    ]);

    $slices = app(PortfolioAccounts::class)->breakdownFor($user->id, $wallet->id);

    expect($slices)->toHaveCount(2)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->label)->toBe('Actions')
        ->and($slices[0]->value)->toBe(1000.0)
        ->and($slices[0]->share)->toBe(80.0)
        ->and($slices[1]->key)->toBe('crypto')
        ->and($slices[1]->share)->toBe(20.0);
});

it('ne ventile rien pour une enveloppe sans position', function () {
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    expect(app(PortfolioAccounts::class)->breakdownFor($user->id, $empty->id))->toBe([]);
});

it('ne sert pas une enveloppe sans position ni espèces', function () {
    ['user' => $user] = portfolioFixture();
    // Sans la moindre transaction : contrairement aux enveloppes de `PortfolioAccountsTest`,
    // celle-ci n'a même pas le versement déduit automatique que déclenche l'observateur de
    // transaction — elle est réellement vide, en base comme dans le domaine.
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    expect(app(PortfolioAccounts::class)->accountFor($user->id, $empty->id))->toBeNull();
});
