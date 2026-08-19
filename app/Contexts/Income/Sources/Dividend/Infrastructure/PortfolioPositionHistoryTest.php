<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

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
