<?php

use App\Contexts\Wealth\Actions\GetWalletPositions;

it('rend les positions de l\'enveloppe', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $positions = app(GetWalletPositions::class)($user->id, $wallet->id);

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id);
});
