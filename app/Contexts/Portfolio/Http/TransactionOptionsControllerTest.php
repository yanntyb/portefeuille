<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Wallet;

it('offers the wallets of the user and nobody else\'s', function () {
    ['user' => $user] = portfolioFixture();
    Wallet::factory()->for($user)->pea()->create();
    Wallet::factory()->create(['name' => 'Ailleurs']);

    $wallets = $this->getJson('/transactions/options')->assertOk()->json('wallets');

    expect(array_column($wallets, 'name'))->toBe(['Compte-titres', 'PEA'])
        ->and($wallets[0]['accountType'])->toBe('cto')
        ->and($wallets[0]['accountTypeLabel'])->toBe('CTO')
        ->and($wallets[1]['accountTypeLabel'])->toBe('PEA');
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

it('names the two directions of an operation from the enum', function () {
    portfolioFixture();

    /** Le front ne recode pas « Achat » et « Vente » : la liste des opérations les tient d'ici. */
    expect($this->getJson('/transactions/options')->json('types'))
        ->toBe([
            ['value' => 'buy', 'label' => 'Achat'],
            ['value' => 'sell', 'label' => 'Vente'],
        ]);
});

it('keeps the key order of its payload', function () {
    portfolioFixture();

    $body = $this->getJson('/transactions/options')->json();

    expect(array_keys($body))->toBe(['wallets', 'instruments', 'types'])
        ->and(array_keys($body['wallets'][0]))->toBe(['id', 'name', 'accountType', 'accountTypeLabel'])
        ->and(array_keys($body['instruments'][0]))
        ->toBe(['id', 'name', 'ticker', 'assetClass', 'assetClassLabel', 'lastPrice']);
});

it('rends empty lists rather than failing on a database with no user', function () {
    expect($this->getJson('/transactions/options')->assertOk()->json())
        ->toBe([
            'wallets' => [],
            'instruments' => [],
            'types' => [
                ['value' => 'buy', 'label' => 'Achat'],
                ['value' => 'sell', 'label' => 'Vente'],
            ],
        ]);
});
