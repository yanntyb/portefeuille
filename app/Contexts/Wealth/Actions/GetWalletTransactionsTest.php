<?php

use App\Contexts\Wealth\Actions\GetWalletTransactions;

it('rend les opérations de l\'enveloppe', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $lines = app(GetWalletTransactions::class)($user->id, $wallet->id);

    /**
     * L'achat de la fixture n'est couvert par aucun dépôt : l'observateur de transaction en
     * déduit un versement du même jour (`RecomputeCashDeposits`), deux lignes désormais.
     */
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->walletId)->toBe($wallet->id);
});
