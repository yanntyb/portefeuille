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
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->where('instrument.name', 'ACME')
            ->where('instrument.position.marketValue', fn ($v) => (float) $v === 1000.0)
            ->has('instrument.transactions', 1)
            ->missing('priceHistory')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('priceHistory.labels', 1)
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
