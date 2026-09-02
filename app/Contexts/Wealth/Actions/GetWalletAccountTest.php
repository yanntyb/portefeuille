<?php

use App\Contexts\Wealth\Actions\GetWalletAccount;

it('rend l\'enveloppe du porteur', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    expect(app(GetWalletAccount::class)($user->id, $wallet->id)?->walletId)->toBe($wallet->id);
});

it('ne rend rien pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    expect(app(GetWalletAccount::class)($user->id, $foreign->id))->toBeNull();
});
