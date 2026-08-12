<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders a held instrument sheet with its position and transactions', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 80]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->where('instrument.name', 'ACME')
            ->where('instrument.position.marketValue', fn ($v) => (float) $v === 1000.0)
            ->has('instrument.transactions', 1)
            ->has('performances', 4)
            ->where('performances.0.key', 'YTD')
            ->where('performances.0.startDate', '2026-01-01')
            ->missing('priceHistory')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('priceHistory.labels', 2)
            )
        );
});

it('hides the position when the instrument is not held', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->where('instrument.position', null)
        );
});

it('returns 404 for an unknown instrument', function () {
    User::query()->delete();
    User::factory()->create();

    $this->get('/instruments/999')->assertNotFound();
});

it('defers the per-title valuation series and loads it on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->missing('valuation')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels', 1)
                ->has('valuation.valuations', 1)
                ->has('valuation.invested', 1)
                ->has('valuation.prices', 1)
            )
        );
});

it('accepts range and granularity query params for the valuation series', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}?range=1M&granularity=week")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels')
                ->has('valuation.prices')
            )
        );
});

it('falls back to defaults for invalid range and granularity', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}?range=bogus&granularity=bogus")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('valuation.labels'))
        );
});
