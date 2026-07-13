<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('renders both dashboard time-series charts with the legend on the left', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    foreach (['ACME', 'GLOBEX'] as $name) {
        $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => $name]);
        Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_cost' => 80,
        ]);
        Transaction::factory()->buy()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'unit_price' => 80,
            'date' => '2026-01-01',
        ]);
    }

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Évolution')
        ->assertSee('Investi par titre')
        ->assertSee('Valeur')
        ->assertSee('GLOBEX')
        ->assertCount('.apx-legend-position-left', 2)
        ->assertNoJavaScriptErrors();
});
