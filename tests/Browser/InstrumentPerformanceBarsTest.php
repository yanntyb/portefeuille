<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentWithPerformances(): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);

    foreach (['2026-01-01' => 100, '2026-07-01' => 120] as $date => $close) {
        Price::factory()->create(['asset_id' => $asset->id, 'date' => $date, 'close' => $close]);
    }

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'date' => '2026-01-01',
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    return ['user' => $user, 'instrument' => $asset];
}

it('replaces the performance table with one bar per period', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithPerformances();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-testid=performance-table]').length", 0)
        ->assertScript("document.querySelectorAll('[data-perf-row]').length", 5)
        ->assertScript("document.querySelector('[data-perf-row] [data-perf-label]').textContent.trim()", 'YTD')
        ->assertNoJavaScriptErrors();
});

it('scales the widest bar to the full track', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithPerformances();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-perf-bar]')).some(el => el.style.width === '100%')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('keeps the starting value and the contributions in the row tooltip', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithPerformances();

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "document.querySelector('[data-perf-row]').title.includes('Valeur début') && document.querySelector('[data-perf-row]').title.includes('Apports')",
            true,
        )
        ->assertNoJavaScriptErrors();
});
