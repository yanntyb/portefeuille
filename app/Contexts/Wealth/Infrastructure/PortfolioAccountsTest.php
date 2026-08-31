<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
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
