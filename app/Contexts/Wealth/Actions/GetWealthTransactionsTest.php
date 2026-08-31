<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Actions\GetWealthTransactions;

it('réunit les opérations de tous les actifs, la plus récente en tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);

    $buy = Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 2,
        'unit_price' => 300,
        'fees' => 1.5,
        'date' => '2026-03-04',
    ]);

    $lines = app(GetWealthTransactions::class)($user->id);

    expect($lines)->toHaveCount(2)
        /** L'identifiant et l'enveloppe ouvrent l'édition depuis la liste. */
        ->and($lines[0]->id)->toBe($buy->id)
        ->and($lines[0]->walletId)->toBe($wallet->id)
        ->and($lines[0]->date)->toBe('2026-03-04')
        ->and($lines[0]->assetId)->toBe($bitcoin->id)
        ->and($lines[0]->assetName)->toBe('Bitcoin')
        ->and($lines[0]->typeLabel)->toBe('Achat')
        ->and($lines[0]->isSell)->toBeFalse()
        ->and($lines[0]->fees)->toBe(1.5)
        /** Flux réel de l'achat : 2 × 300 sortis du compte, plus les 1,50 € de frais. */
        ->and($lines[0]->total)->toBe(601.5)
        ->and($lines[1]->date)->toBe('2026-01-01')
        ->and($lines[1]->assetName)->toBe('ACME');
});

it('ne rend que les opérations de l\'utilisateur demandé', function () {
    ['user' => $user] = portfolioFixture();
    portfolioFixture(['name' => 'Globex', 'ticker' => 'GBX']);

    $lines = app(GetWealthTransactions::class)($user->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->assetName)->toBe('ACME');
});

it('marque la vente et lui garde son montant en positif', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 3,
        'unit_price' => 120,
        'date' => '2026-06-01',
    ]);

    $lines = app(GetWealthTransactions::class)($user->id);

    expect($lines[0]->isSell)->toBeTrue()
        ->and($lines[0]->typeLabel)->toBe('Vente')
        /** Le sens du flux se lit sur `isSell` : le montant reste ce que la ligne a pesé. */
        ->and($lines[0]->total)->toBe(360.0);
});

it('saute l\'opération qui ne porte aucun actif', function () {
    ['user' => $user] = portfolioFixture();

    Transaction::query()->where('user_id', $user->id)->update(['asset_id' => null]);

    expect(app(GetWealthTransactions::class)($user->id))->toBe([]);
});

it('ne rend rien à un utilisateur sans opération', function () {
    $orphan = Wallet::factory()->create();

    expect(app(GetWealthTransactions::class)($orphan->user_id))->toBe([]);
});
