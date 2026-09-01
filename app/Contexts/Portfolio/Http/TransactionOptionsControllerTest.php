<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Wallet;

it('offers the wallets of the user and nobody else\'s', function () {
    ['user' => $user] = portfolioFixture();
    Wallet::factory()->for($user)->pea()->create(['broker' => 'IBKR']);
    Wallet::factory()->create(['name' => 'Ailleurs']);

    $wallets = $this->getJson('/transactions/options')->assertOk()->json('wallets');

    expect(array_column($wallets, 'name'))->toBe(['Compte-titres', 'PEA'])
        ->and($wallets[0]['accountType'])->toBe('cto')
        ->and($wallets[0]['accountTypeLabel'])->toBe('CTO')
        /** Sans établissement en base, la clé reste là et nulle : le front y retombe sur le nom. */
        ->and($wallets[0]['broker'])->toBeNull()
        ->and($wallets[1]['accountTypeLabel'])->toBe('PEA')
        ->and($wallets[1]['broker'])->toBe('IBKR');
});

it('offers the whole catalogue, held or not, with its last close', function () {
    portfolioFixture();
    Instrument::factory()->ofType(InstrumentType::Crypto)->create([
        'name' => 'Bitcoin',
        'ticker' => 'BTC-EUR',
        'asset_class' => AssetClass::Crypto,
    ]);

    $instruments = $this->getJson('/transactions/options')->assertOk()->json('instruments');

    /** Bitcoin n'est détenu par personne : la saisie sert justement à entrer un premier achat. */
    expect(array_column($instruments, 'name'))->toBe(['ACME', 'Bitcoin'])
        /** Cast : un flottant rond traverse le JSON en entier, ce dont JavaScript ne fait pas cas. */
        ->and((float) $instruments[0]['lastPrice'])->toBe(100.0)
        ->and($instruments[0]['assetClassLabel'])->toBe('Actions')
        /** Jamais coté : prix inconnu, donc nul — et non zéro, qui serait un prix. */
        ->and($instruments[1]['lastPrice'])->toBeNull()
        ->and($instruments[1]['assetClassLabel'])->toBe('Crypto');
});

it('tells what each wallet holds of each asset, and nobody else\'s', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    portfolioFixture();

    $held = $this->actingAs($user)->getJson('/transactions/options')->assertOk()->json('held');

    /** De quoi plafonner une vente à la frappe ; le serveur reste seul juge de la survente. */
    expect($held)->toHaveCount(1)
        ->and($held[0]['walletId'])->toBe($wallet->id)
        ->and($held[0]['assetId'])->toBe($instrument->id)
        ->and((float) $held[0]['quantity'])->toBe(10.0);
});

it('names every kind of operation from the enum', function () {
    portfolioFixture();

    /** Le front ne recode aucun libellé : la liste des opérations les tient toutes d'ici. */
    expect($this->getJson('/transactions/options')->json('types'))
        ->toBe([
            ['value' => 'buy', 'label' => 'Achat'],
            ['value' => 'sell', 'label' => 'Vente'],
            ['value' => 'deposit', 'label' => 'Versement'],
            ['value' => 'withdrawal', 'label' => 'Retrait'],
            ['value' => 'dividend', 'label' => 'Dividende'],
        ]);
});

it('keeps the key order of its payload', function () {
    portfolioFixture();

    $body = $this->getJson('/transactions/options')->json();

    expect(array_keys($body))->toBe(['wallets', 'instruments', 'held', 'types'])
        ->and(array_keys($body['wallets'][0]))->toBe(['id', 'name', 'broker', 'accountType', 'accountTypeLabel'])
        ->and(array_keys($body['instruments'][0]))
        ->toBe(['id', 'name', 'ticker', 'assetClass', 'assetClassLabel', 'lastPrice'])
        ->and(array_keys($body['held'][0]))->toBe(['walletId', 'assetId', 'quantity']);
});

it('rends empty lists rather than failing on a database with no user', function () {
    expect($this->getJson('/transactions/options')->assertOk()->json())
        ->toBe([
            'wallets' => [],
            'instruments' => [],
            'held' => [],
            'types' => [
                ['value' => 'buy', 'label' => 'Achat'],
                ['value' => 'sell', 'label' => 'Vente'],
                ['value' => 'deposit', 'label' => 'Versement'],
                ['value' => 'withdrawal', 'label' => 'Retrait'],
                ['value' => 'dividend', 'label' => 'Dividende'],
            ],
        ]);
});
