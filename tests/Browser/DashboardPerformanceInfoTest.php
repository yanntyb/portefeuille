<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('explains the period performances through an info dialog', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->startOfYear(), 'close' => 80]);
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

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Performance par période')
        ->click('[aria-label="Comment lire les performances par période"]')
        ->assertSee('Comment lire les performances par période')
        ->assertSee('cumulés, pas annualisés')
        ->assertSee('Apports')
        ->assertSee('les versements de la période')
        ->assertSee('la date de tes versements ne change rien')
        ->assertNoJavascriptErrors();
});
