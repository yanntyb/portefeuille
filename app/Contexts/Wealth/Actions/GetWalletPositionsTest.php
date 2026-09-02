<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWalletPositions;

it('rend les positions de l\'enveloppe', function () {
    /**
     * Porteur jetable avant la fixture : sous `RefreshDatabase`, le premier utilisateur créé et
     * sa première enveloppe reçoivent sinon le même id, ce qui rendrait une inversion des deux
     * arguments de l'action indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $positions = app(GetWalletPositions::class)($user->id, $wallet->id);

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id);
});
