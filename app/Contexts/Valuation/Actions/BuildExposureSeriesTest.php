<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildExposureSeries;

it('rend la série totale de l\'exposition demandée', function () {
    ['user' => $user] = cryptoFixture();

    $series = app(BuildExposureSeries::class)($user->id, [AssetClass::Equity]);
    $valuations = $series->valuations;

    expect($series->labels)->not->toBeEmpty()
        ->and($valuations)->toHaveCount(count($series->labels))
        ->and(end($valuations))->toBe(1000.0);
});

it('ne mêle pas deux expositions sous le même nom de cache', function () {
    ['user' => $user, 'crypto' => $crypto] = cryptoFixture();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $crypto->id,
        'quantity' => 1,
        'unit_price' => 300,
        'date' => '2026-01-01',
    ]);

    $equitySeries = app(BuildExposureSeries::class)($user->id, [AssetClass::Equity]);
    $cryptoSeries = app(BuildExposureSeries::class)($user->id, [AssetClass::Crypto]);
    $equityValuations = $equitySeries->valuations;
    $cryptoValuations = $cryptoSeries->valuations;

    expect(end($equityValuations))->not->toBe(end($cryptoValuations));
});

it('rend une série vide pour un utilisateur sans transaction', function () {
    expect(app(BuildExposureSeries::class)(0, [AssetClass::Equity])->labels)->toBe([]);
});

it('garde le cash sans actif malgré le filtre par classe', function () {
    ['user' => $user] = cryptoFixture();

    /**
     * Un versement sans `asset_id`, sur une enveloppe distincte de celle qui détient le titre
     * filtré : le cash est global à l'utilisateur, il doit traverser le filtre de classe même si
     * aucune ligne ne porte l'exposition demandée.
     */
    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'date' => '2025-01-01',
        'amount' => 500,
    ]);

    $series = app(BuildExposureSeries::class)($user->id, [AssetClass::Equity]);
    $cash = $series->cash;

    // Le versement du titre est financé par sa propre ligne déduite (achat -800, versement +800,
    // net nul) ; seul le versement libre de 500 doit rester dans le cash de fin de série.
    expect(end($cash))->toBe(500.0);
});
