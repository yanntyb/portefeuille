<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWalletBreakdown;

it('ventile l\'enveloppe par classe d\'actif', function () {
    /**
     * Porteur jetable avant la fixture : sous `RefreshDatabase`, le premier utilisateur créé et
     * sa première enveloppe reçoivent sinon le même id, ce qui rendrait une inversion des deux
     * arguments de l'action indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $slices = app(GetWalletBreakdown::class)($user->id, $wallet->id);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->share)->toBe(100.0);
});
