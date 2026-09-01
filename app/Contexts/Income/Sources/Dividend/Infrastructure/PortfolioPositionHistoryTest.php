<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('rend le portefeuille des transactions avec l\'enveloppe qui les porte', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);

    $records = app(PositionHistoryPort::class)->transactionsFor($user->id);

    expect($records[0]->walletId)->toBe($wallet->id);
});

it('rend les mouvements de l\'utilisateur, sens compris', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-02-01', 'quantity' => 4, 'unit_price' => 90,
    ]);

    $records = app(PositionHistoryPort::class)->transactionsFor($user->id);

    expect($records)->toHaveCount(2)
        ->and($records[0]->isSell)->toBeFalse()
        ->and($records[0]->quantity)->toBe(10.0)
        ->and($records[1]->isSell)->toBeTrue()
        ->and($records[1]->quantity)->toBe(4.0);
});

it('ignore les transactions d\'un autre utilisateur et celles sans actif', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    Transaction::factory()->buy()->create(['user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other), 'asset_id' => Instrument::factory()]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => null]);

    expect(app(PositionHistoryPort::class)->transactionsFor($user->id))->toBe([])
        ->and(app(PositionHistoryPort::class)->assetIdsFor($user->id))->toBe([]);
});

it('liste les actifs mouvementés une seule fois', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->count(2)->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
    ]);

    expect(app(PositionHistoryPort::class)->assetIdsFor($user->id))->toBe([$instrument->id]);
});

it('agrège la position courante de toutes les enveloppes, prix de revient pondéré', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 100,
    ]);

    $position = app(PositionHistoryPort::class)->positionFor($user->id, $instrument->id);

    expect($position->quantity)->toBe(20.0)
        ->and($position->avgCost)->toBe(90.0);
});

it('rend null sans position sur l\'actif', function () {
    $user = User::factory()->create();

    expect(app(PositionHistoryPort::class)->positionFor($user->id, 404))->toBeNull();
});

it('rend la position courante de chaque actif détenu, enveloppes agrégées', function () {
    $user = User::factory()->create();
    $held = Instrument::factory()->create();
    $other = Instrument::factory()->create();
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $held->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $held->id,
        'quantity' => 10, 'avg_cost' => 100,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $other->id,
        'quantity' => 3, 'avg_cost' => 50,
    ]);

    $positions = app(PositionHistoryPort::class)->positionsFor($user->id);

    expect($positions)->toHaveCount(2)
        ->and($positions[$held->id]->quantity)->toBe(20.0)
        ->and($positions[$held->id]->avgCost)->toBe(90.0)
        ->and($positions[$other->id]->quantity)->toBe(3.0);
});

it('ne rend que les positions de l\'utilisateur demandé', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Holding::factory()->create([
        'user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other), 'asset_id' => Instrument::factory(),
        'quantity' => 10, 'avg_cost' => 80,
    ]);

    expect(app(PositionHistoryPort::class)->positionsFor($user->id))->toBe([]);
});

it('rend les dividendes encaissés en transaction, enveloppe et actif compris', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->dividend()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-02-01', 'fees' => 0, 'amount' => 34.90,
    ]);

    $confirmed = app(PositionHistoryPort::class)->confirmedDividendsFor($user->id);

    expect($confirmed)->toHaveCount(1)
        ->and($confirmed[0]->assetId)->toBe($instrument->id)
        ->and($confirmed[0]->walletId)->toBe($wallet->id)
        ->and($confirmed[0]->exDate)->toBe('2026-02-01')
        ->and($confirmed[0]->amount)->toBe(34.90);
});

it('ne rend que les dividendes encaissés de l\'utilisateur demandé', function () {
    $other = User::factory()->create();
    Transaction::factory()->dividend()->create([
        'user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other), 'asset_id' => Instrument::factory(),
        'date' => '2026-02-01', 'fees' => 0, 'amount' => 10.0,
    ]);

    $user = User::factory()->create();

    expect(app(PositionHistoryPort::class)->confirmedDividendsFor($user->id))->toBe([]);
});
