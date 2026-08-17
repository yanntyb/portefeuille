<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function userWithEvolution(): User
{
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 137.3]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(20)->format('Y-m-d'), 'close' => 152.7]);
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

    return $user;
}

it('leads the dashboard with the chart alone, without a header row', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript("document.querySelectorAll('[data-section=evolution] h2').length", 0)
        ->assertScript("document.querySelectorAll('[data-section=evolution] [data-chart]').length", 1)
        ->assertNoJavaScriptErrors();
});

it('labels the value axis with the exact extremes, and nothing between them', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const texts = document.querySelectorAll('[data-section=evolution] [data-chart] svg text');
                const values = [...texts]
                    .filter((text) => text.textContent.includes('€'))
                    .map((text) => Number(text.textContent.replace(/\\D/g, '')));
                return [values.length, Math.max(...values)].join('|');
            })()",
            '2|1527',
        )
        ->assertNoJavaScriptErrors();
});

it('pads the evolution chart like the instrument valuation chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const section = document.querySelector('[data-section=evolution]');
                const wrapper = section.querySelector('[data-chart]').closest('section > div');
                const styles = getComputedStyle(wrapper);
                return [
                    getComputedStyle(section).paddingLeft,
                    styles.paddingLeft,
                    styles.paddingRight,
                ].join('|');
            })()",
            '0px|24px|24px',
        )
        ->assertNoJavaScriptErrors();
});
