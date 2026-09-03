<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetRealizedGains;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('réunit les enveloppes d\'un actif en une position valorisée', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    $positions = app(GetPortfolioPositions::class)($user->id);

    expect($positions)->toHaveKey($instrument->id);

    $position = $positions[$instrument->id];

    expect($position->quantity)->toBe(14.0)
        ->and($position->avgCost)->toBe((10.0 * 80.0 + 4.0 * 95.0) / 14.0)
        ->and($position->marketValue)->toBe(1400.0);
});

it('rend un tableau vide pour un utilisateur inconnu', function () {
    expect(app(GetPortfolioPositions::class)(0))->toBe([]);
});

/** `PositionLineData::realizedGain` est câblé sur `GetRealizedGains`, pas recalculé à part. */
it('porte le gain déjà réalisé d\'une position, câblé sur GetRealizedGains', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'close' => 100.0]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01',
        'amount' => 1000, 'auto' => false,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 80, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 5, 'unit_price' => 100, 'fees' => 0,
    ]);

    $position = app(GetPortfolioPositions::class)($user->id)[$asset->id];
    $expected = app(GetRealizedGains::class)($user->id)[$asset->id];

    expect($position->realizedGain)->toBe($expected)
        ->and($position->realizedGain)->toBe(100.0);
});
