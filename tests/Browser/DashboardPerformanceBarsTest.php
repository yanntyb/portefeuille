<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
 */
function portfolioWithPerformances(): User
{
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

    return $user;
}

it('renders one bar per period, like the instrument page', function () {
    $this->actingAs(portfolioWithPerformances());

    $page = visit('/');

    $page->assertSee('Performances')
        ->assertScript("document.querySelectorAll('[data-section=performances] [data-perf-row]').length", 5)
        ->assertScript(
            "document.querySelector('[data-section=performances] [data-perf-row] [data-perf-label]').textContent.trim()",
            'YTD',
        )
        ->assertScript(
            "document.querySelector('[data-section=performances] [data-perf-row]:last-child [data-perf-label]').textContent.trim()",
            'Max',
        )
        ->assertNoJavaScriptErrors();
});

it('scales the widest bar to the full track', function () {
    $this->actingAs(portfolioWithPerformances());

    visit('/')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=performances] [data-perf-bar]')).some(el => el.style.width === '100%')",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('keeps the starting value and the contributions in the row tooltip', function () {
    $this->actingAs(portfolioWithPerformances());

    visit('/')
        ->assertScript(
            "document.querySelector('[data-section=performances] [data-perf-row]').title.includes('Valeur début') && document.querySelector('[data-section=performances] [data-perf-row]').title.includes('Apports')",
            true,
        )
        ->assertNoJavaScriptErrors();
});
