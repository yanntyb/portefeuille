<?php

use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Models\Holding;
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
        ->and($position->lastPrice)->toBe(100.0)
        ->and($position->marketValue)->toBe(1400.0);
});

it('rend un tableau vide pour un utilisateur inconnu', function () {
    expect(app(GetPortfolioPositions::class)(0))->toBe([]);
});
