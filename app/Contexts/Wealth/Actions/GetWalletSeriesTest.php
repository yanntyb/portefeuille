<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\GetWalletSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;

it('rend la série de valorisation de l\'enveloppe', function () {
    /**
     * Porteur jetable avant la fixture : sous `RefreshDatabase`, le premier utilisateur créé et
     * sa première enveloppe reçoivent sinon le même id, ce qui rendrait une inversion des deux
     * arguments de l'action indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $series = app(GetWalletSeries::class)($user->id, $wallet->id);
    $value = $series->value;

    expect($series)->toBeInstanceOf(ClassSeriesData::class)
        ->and($series->labels)->not->toBeEmpty()
        ->and(end($value))->toBe(1000.0);
});
