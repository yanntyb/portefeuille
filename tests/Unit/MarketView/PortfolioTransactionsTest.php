<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\MarketView\Infrastructure\PortfolioTransactions;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->adapter = new PortfolioTransactions;
});

it('returns transactions for a user and asset, newest first', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80, 'fees' => 1]);
    Transaction::factory()->sell()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-03-01', 'quantity' => 4, 'unit_price' => 100, 'fees' => 2]);

    $lines = $this->adapter->transactionsFor($user->id, $asset->id);

    expect($lines)->toHaveCount(2);
    expect($lines[0]->date)->toBe('2026-03-01');
    expect($lines[0]->isSell)->toBeTrue();
    expect($lines[0]->typeLabel)->toBe('Vente');
    expect($lines[0]->total)->toBe(400.0);
    expect($lines[1]->date)->toBe('2026-01-01');
    expect($lines[1]->typeLabel)->toBe('Achat');
    expect($lines[1]->total)->toBe(800.0);
});

it('excludes other users and other assets', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    $otherAsset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $otherAsset->id]);

    expect($this->adapter->transactionsFor($user->id, $asset->id))->toBeEmpty();
});
