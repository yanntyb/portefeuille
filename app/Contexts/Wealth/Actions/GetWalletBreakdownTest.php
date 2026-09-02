<?php

use App\Contexts\Wealth\Actions\GetWalletBreakdown;

it('ventile l\'enveloppe par classe d\'actif', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $slices = app(GetWalletBreakdown::class)($user->id, $wallet->id);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->share)->toBe(100.0);
});
