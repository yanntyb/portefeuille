<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\MarketView\Infrastructure\PortfolioHoldings;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->adapter = app(PortfolioHoldings::class);
});

it('returns holdings for a user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Holding::factory()->create(['user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other)->create()->id, 'asset_id' => $asset->id, 'quantity' => 5, 'avg_cost' => 50]);

    $holdings = $this->adapter->holdingsFor($user->id);

    expect($holdings)->toHaveCount(1);
    expect($holdings[0]->assetId)->toBe($asset->id);
    expect($holdings[0]->quantity)->toBe(10.0);
    expect($holdings[0]->avgCost)->toBe(80.0);
});

it('returns a single holding for a user and asset', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 3, 'avg_cost' => 40]);

    expect($this->adapter->holdingFor($user->id, $asset->id)->quantity)->toBe(3.0);
    expect($this->adapter->holdingFor($user->id, 999))->toBeNull();
});

it('aggregates the same asset held across multiple wallets', function () {
    $user = User::factory()->create();
    $walletA = Wallet::factory()->for($user)->create();
    $walletB = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $walletA->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $walletB->id, 'asset_id' => $asset->id, 'quantity' => 30, 'avg_cost' => 200]);

    $holdings = $this->adapter->holdingsFor($user->id);

    expect($holdings)->toHaveCount(1);
    expect($holdings[0]->quantity)->toBe(40.0);
    expect($holdings[0]->avgCost)->toBe(175.0); // (10*100 + 30*200) / 40
    expect($this->adapter->holdingFor($user->id, $asset->id)->quantity)->toBe(40.0);
});
