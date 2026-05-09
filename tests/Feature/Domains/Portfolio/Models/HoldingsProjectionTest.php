<?php

namespace Tests\Feature\Domains\Portfolio\Models;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\HoldingsProjection;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;
use App\Domains\Asset\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates projection on first buy', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $stock = Stock::factory()->create();

    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock, 'asset')
        ->create([
            'type' => TransactionType::Buy,
            'quantity' => 10,
            'unit_price' => 100.0,
        ]);

    $projection = HoldingsProjection::where('asset_id', $stock->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect($projection)->not->toBeNull();
    expect((float) $projection->quantity)->toBe(10.0);
    expect((float) $projection->avg_cost)->toBe(100.0);
});

it('updates projection on second buy', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $stock = Stock::factory()->create();

    // First buy: 10 @ 100
    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock, 'asset')
        ->create([
            'type' => TransactionType::Buy,
            'quantity' => 10,
            'unit_price' => 100.0,
        ]);

    // Second buy: 5 @ 120
    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock, 'asset')
        ->create([
            'type' => TransactionType::Buy,
            'quantity' => 5,
            'unit_price' => 120.0,
        ]);

    $projection = HoldingsProjection::where('asset_id', $stock->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $projection->quantity)->toBe(15.0);
    // Weighted average: (10*100 + 5*120) / 15 = 106.67
    $avgCost = (float) $projection->avg_cost;
    expect($avgCost)->toBeGreaterThan(106.5)->toBeLessThan(106.8);
});

it('reduces projection on sell', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $stock = Stock::factory()->create();

    // Buy: 10 @ 100
    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock, 'asset')
        ->create([
            'type' => TransactionType::Buy,
            'quantity' => 10,
            'unit_price' => 100.0,
        ]);

    // Sell: 3 @ 150
    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock, 'asset')
        ->create([
            'type' => TransactionType::Sell,
            'quantity' => 3,
            'unit_price' => 150.0,
        ]);

    $projection = HoldingsProjection::where('asset_id', $stock->id)
        ->where('wallet_id', $wallet->id)
        ->first();

    expect((float) $projection->quantity)->toBe(7.0);
    expect((float) $projection->avg_cost)->toBe(100.0);
});

it('separates projections by asset', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $stock1 = Stock::factory()->create();
    $stock2 = Stock::factory()->create();

    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock1, 'asset')
        ->create(['quantity' => 10, 'unit_price' => 100.0]);

    Transaction::factory()
        ->for($user)
        ->for($wallet)
        ->for($stock2, 'asset')
        ->create(['quantity' => 5, 'unit_price' => 200.0]);

    $projections = HoldingsProjection::where('wallet_id', $wallet->id)->get();

    expect($projections)->toHaveCount(2);
    expect($projections->firstWhere('asset_id', $stock1->id))->not->toBeNull();
    expect($projections->firstWhere('asset_id', $stock2->id))->not->toBeNull();
});

it('separates projections by wallet', function () {
    $user = User::factory()->create();
    $wallet1 = Wallet::factory()->for($user)->create();
    $wallet2 = Wallet::factory()->for($user)->create();
    $stock = Stock::factory()->create();

    Transaction::factory()
        ->for($user)
        ->for($wallet1)
        ->for($stock, 'asset')
        ->create(['quantity' => 10]);

    Transaction::factory()
        ->for($user)
        ->for($wallet2)
        ->for($stock, 'asset')
        ->create(['quantity' => 20]);

    $w1Projection = HoldingsProjection::where('asset_id', $stock->id)
        ->where('wallet_id', $wallet1->id)
        ->first();

    $w2Projection = HoldingsProjection::where('asset_id', $stock->id)
        ->where('wallet_id', $wallet2->id)
        ->first();

    expect((float) $w1Projection->quantity)->toBe(10.0);
    expect((float) $w2Projection->quantity)->toBe(20.0);
});

it('scopes projections to user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $wallet1 = Wallet::factory()->for($user1)->create();
    $wallet2 = Wallet::factory()->for($user2)->create();
    $stock = Stock::factory()->create();

    Transaction::factory()
        ->for($user1)
        ->for($wallet1)
        ->for($stock, 'asset')
        ->create(['quantity' => 10]);

    $projection = HoldingsProjection::where('user_id', $user2->id)->first();
    expect($projection)->toBeNull();
});
