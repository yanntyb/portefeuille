<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the instrument catalogue with a held flag', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co', 'ticker' => 'HLD', 'isin' => 'FR0000000001']);
    Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $held->id, 'quantity' => 10, 'avg_cost' => 80]);

    $this->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Index')
            ->has('catalog.lines', 2)
            ->where('catalog.lines.1.held', true)
            ->where('catalog.lines.1.ticker', 'HLD')
            ->where('catalog.lines.1.isin', 'FR0000000001')
        );
});

it('defers the catalogue trends and loads them on demand', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'Trending Co']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Index')
            ->where('catalogRange', 'max')
            ->missing('trends')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trends', 1)
                ->where('trends.0.assetId', $asset->id)
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});

it('accepts the range query param for the catalogue trends', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subMonths(6), 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/instruments?range=1M')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Index')
            ->where('catalogRange', '1M')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});
