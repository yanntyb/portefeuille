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

/**
 * Le filtre par classe laisse passer les mouvements sans `asset_id` : c'est ce que veut la série
 * de valorisation, qui n'en tire aucune position ni aucun investi. La série de liquidités, elle,
 * n'est plus ici — elle se lit sur `PortfolioCash::seriesFor()`.
 */
it('laisse un mouvement d\'espèces traverser le filtre sans troubler la série', function () {
    ['user' => $user] = cryptoFixture();

    $before = app(BuildExposureSeries::class)($user->id, [AssetClass::Crypto]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'date' => '2025-01-01',
        'amount' => 500,
    ]);

    $after = app(BuildExposureSeries::class)($user->id, [AssetClass::Crypto]);

    expect($after->labels)->toBe($before->labels)
        ->and($after->valuations)->toBe($before->valuations)
        ->and($after->invested)->toBe($before->invested);
});
