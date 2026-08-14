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

it('lines the period picker up with the section title, like the instrument chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const title = document.querySelector('[data-section=evolution] h2');
                const button = document.querySelector('[data-section=evolution] button');
                const row = title.closest('section > div');
                return row.contains(button) && getComputedStyle(row).justifyContent === 'space-between';
            })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('pads the evolution chart like the instrument valuation chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const section = document.querySelector('[data-section=evolution]');
                const wrapper = section.querySelector('.apexcharts-canvas').closest('section > div');
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
