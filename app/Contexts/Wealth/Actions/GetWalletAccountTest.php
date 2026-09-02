<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWalletAccount;

it('rend l\'enveloppe du porteur', function () {
    /**
     * Porteur jetable avant la fixture : sous `RefreshDatabase`, le premier utilisateur créé et
     * sa première enveloppe reçoivent sinon le même id, ce qui rendrait une inversion des deux
     * arguments de l'action indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    expect(app(GetWalletAccount::class)($user->id, $wallet->id)?->walletId)->toBe($wallet->id);
});

it('ne rend rien pour l\'enveloppe d\'un autre porteur', function () {
    User::factory()->create();
    ['user' => $userA] = portfolioFixture();

    User::factory()->create();
    ['user' => $userB, 'wallet' => $walletB] = portfolioFixture();

    /**
     * Les deux sens ferment la symétrie : sans le second appel, un porteur et une enveloppe qui
     * ne se correspondent pas rendraient `null` quel que soit l'ordre des arguments.
     */
    expect(app(GetWalletAccount::class)($userA->id, $walletB->id))->toBeNull()
        ->and(app(GetWalletAccount::class)($userB->id, $walletB->id))->not->toBeNull();
});
