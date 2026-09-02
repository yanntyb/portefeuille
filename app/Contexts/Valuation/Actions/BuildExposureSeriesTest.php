<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
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

/**
 * Le filtre par enveloppe est plus franc que celui par classe : le cash étant tenu par wallet, un
 * versement sur le compte voisin n'a rien à faire dans la série de celui-ci.
 */
it('ne retient que les transactions de l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $mine = app(BuildExposureSeries::class)($user->id, null, $wallet->id);
    $theirs = app(BuildExposureSeries::class)($user->id, null, $other->id);
    $valuations = $mine->valuations;

    expect(end($valuations))->toBe(1000.0)
        ->and($theirs->labels)->toBe([]);
});

/**
 * Le versement seul ne prouve rien : sans `asset_id`, il ne pèse ni sur les actifs à valoriser
 * ni sur la date de départ de la série, filtre ou pas — un filtre inerte laisserait ce test
 * passer quand même. L'achat du voisin, lui, ferait bouger la série s'il fuyait : c'est donc
 * lui qui porte la preuve que l'enveloppe surveillée reste étanche à ce que fait sa voisine.
 */
it('ne laisse ni l\'achat ni le versement du voisin passer dans la série filtrée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $before = app(BuildExposureSeries::class)($user->id, null, $wallet->id);

    $neighborStock = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Price::factory()->create(['asset_id' => $neighborStock->id, 'date' => now(), 'close' => 50]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $neighborStock->id,
        'quantity' => 10,
        'unit_price' => 50,
        'date' => '2026-01-01',
    ]);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'date' => '2025-01-01',
        'amount' => 500,
    ]);

    /**
     * `BuildExposureSeries` mémoïse ses lectures en scoped (`TransactionHistoryPort`,
     * `SeriesCachePort`) : sans ce reset, les deux appels suivants reserviraient les
     * transactions et le nom de cache lus avant l'achat du voisin, et le test comparerait une
     * série à elle-même au lieu de vérifier l'étanchéité réelle du filtre.
     */
    $this->app->forgetScopedInstances();

    $whole = app(BuildExposureSeries::class)($user->id);
    $filtered = app(BuildExposureSeries::class)($user->id, null, $wallet->id);
    $wholeValuations = $whole->valuations;
    $beforeValuations = $before->valuations;

    expect($filtered->valuations)->toBe($before->valuations)
        ->and($filtered->labels)->toBe($before->labels)
        ->and(end($wholeValuations))->not->toBe(end($beforeValuations));
});

it('ne mêle pas deux enveloppes sous le même nom de cache', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $whole = app(BuildExposureSeries::class)($user->id);
    $one = app(BuildExposureSeries::class)($user->id, null, $wallet->id);
    $none = app(BuildExposureSeries::class)($user->id, null, $other->id);

    expect($whole->valuations)->toBe($one->valuations)
        ->and($none->valuations)->toBe([]);
});
