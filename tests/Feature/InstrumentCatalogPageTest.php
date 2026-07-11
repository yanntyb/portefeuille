<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the instrument catalogue with a held flag', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co']);
    Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $held->id, 'quantity' => 10, 'avg_cost' => 80]);

    $this->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Index')
            ->has('catalog.lines', 2)
        );
});
