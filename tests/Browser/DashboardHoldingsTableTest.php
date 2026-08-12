<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('orders the holdings columns with the value and gain right after the asset', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    $page = visit('/');

    $page->assertScript(
        "Array.from(document.querySelectorAll('thead th')).map(th => th.textContent.trim()).filter(Boolean).join('|')",
        'Actif|Valeur|+/-|Type|Quantité|Dernier prix',
    )->assertNoJavaScriptErrors();
});
