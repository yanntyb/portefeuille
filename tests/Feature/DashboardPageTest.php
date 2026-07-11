<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Dashboard with an empty overview when there is no data', function () {
    // A legacy data migration seeds a hardcoded user on every migrate:fresh, which would
    // otherwise be picked up by the controller's `User::query()->first()` fallback instead
    // of the user created below.
    User::query()->delete();
    User::factory()->create();

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 0.0)
            ->has('overview.holdings', 0)
            ->has('overview.allocation', 0)
        );
});

it('renders the Dashboard with the user portfolio overview', function () {
    // See note above: clear any legacy seeded user so the fallback resolves to this test's user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 1000.0)
            ->where('overview.totalGain', fn ($value) => (float) $value === 200.0)
            ->has('overview.holdings', 1)
            ->has('overview.allocation', 1)
            ->where('overview.holdings.0.assetName', 'ACME')
            ->where('overview.holdings.0.assetId', $asset->id)
        );
});

it('defers the valuation series and loads it on demand', function () {
    // See note above: clear any legacy seeded user so the fallback resolves to this test's user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('valuationSeries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuationSeries.labels', 1)
                ->has('valuationSeries.valuations', 1)
            )
        );
});

it('defers the invested-by-asset series and loads it on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('investedByAsset')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('investedByAsset.series', 1)
                ->where('investedByAsset.series.0.name', 'ACME')
            )
        );
});
