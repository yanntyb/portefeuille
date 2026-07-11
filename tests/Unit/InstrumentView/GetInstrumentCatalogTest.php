<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->action = app(GetInstrumentCatalog::class);
});

it('lists instruments and flags the ones held with value', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co']);
    $notHeld = Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $held->id, 'quantity' => 10, 'avg_cost' => 80]);

    $catalog = ($this->action)($user->id);

    $lines = collect($catalog->lines)->keyBy('id');
    expect($lines[$held->id]->held)->toBeTrue();
    expect($lines[$held->id]->lastPrice)->toBe(100.0);
    expect($lines[$held->id]->marketValue)->toBe(1000.0);
    expect($lines[$notHeld->id]->held)->toBeFalse();
    expect($lines[$notHeld->id]->marketValue)->toBeNull();
});
