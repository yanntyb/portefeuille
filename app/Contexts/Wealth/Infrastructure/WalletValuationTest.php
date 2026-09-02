<?php

use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Ports\ValuationPort;

it('rend la série de l\'enveloppe, valeur et investi alignés sur ses labels', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $wallet->id);
    $value = $series->value;

    expect($series->labels)->not->toBeEmpty()
        ->and($value)->toHaveCount(count($series->labels))
        ->and($series->invested)->toHaveCount(count($series->labels))
        ->and(end($value))->toBe(1000.0);
});

it('rend une série vide pour une enveloppe sans opération', function () {
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $empty->id);

    expect($series->labels)->toBe([])
        ->and($series->value)->toBe([])
        ->and($series->invested)->toBe([]);
});
