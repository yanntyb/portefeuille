<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWalletTransactions;

it('rend les opérations de l\'enveloppe', function () {
    /**
     * Porteur jetable avant la fixture : sous `RefreshDatabase`, le premier utilisateur créé et
     * sa première enveloppe reçoivent sinon le même id, ce qui rendrait une inversion des deux
     * arguments de l'action indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $lines = app(GetWalletTransactions::class)($user->id, $wallet->id);

    /**
     * L'achat de la fixture n'est couvert par aucun dépôt : l'observateur de transaction en
     * déduit un versement du même jour (`RecomputeCashDeposits`), deux lignes désormais.
     */
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->walletId)->toBe($wallet->id);
});
