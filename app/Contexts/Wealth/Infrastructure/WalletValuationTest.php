<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Ports\ValuationPort;

it('rend la série de l\'enveloppe, valeur et investi alignés sur ses labels', function () {
    /**
     * Porteur jetable avant la fixture, et une enveloppe voisine : sous `RefreshDatabase`, le
     * premier utilisateur créé et sa première enveloppe reçoivent sinon le même id, ce qui
     * rendrait une inversion des deux arguments de `seriesForWallet` indétectable.
     */
    User::factory()->create();
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $neighbour = Wallet::factory()->for($user)->create(['name' => 'Enveloppe voisine']);

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $wallet->id);
    $value = $series->value;

    expect($series->labels)->not->toBeEmpty()
        ->and($value)->toHaveCount(count($series->labels))
        ->and($series->invested)->toHaveCount(count($series->labels))
        ->and(end($value))->toBe(1000.0);

    /** L'enveloppe voisine n'a aucune opération : une inversion ferait renvoyer sa série vide. */
    expect(app(ValuationPort::class)->seriesForWallet($user->id, $neighbour->id)->labels)->toBe([]);
});

it('rend une série vide pour une enveloppe sans opération', function () {
    User::factory()->create();
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $empty->id);

    expect($series->labels)->toBe([])
        ->and($series->value)->toBe([])
        ->and($series->invested)->toBe([]);
});
