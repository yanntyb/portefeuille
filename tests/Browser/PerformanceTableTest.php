<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('renders the period performances as a table', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 100,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2026-01-01',
    ]);

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Performance par période')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-testid=performance-table] thead th')).map(th => th.textContent.trim()).join('|')",
            'Période|Depuis|Valeur début|Apports|Gain|Perf.',
        )
        ->assertScript(
            "document.querySelectorAll('[data-testid=performance-table] tbody tr').length",
            5,
        )
        ->assertScript(
            "document.querySelector('[data-testid=performance-table] tbody tr td').textContent.trim()",
            'YTD',
        )
        ->assertScript(
            "document.querySelector('[data-testid=performance-table] tbody tr:last-child td').textContent.trim()",
            'Max',
        )
        ->assertNoJavaScriptErrors();
});
