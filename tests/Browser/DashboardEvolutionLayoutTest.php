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

it('draws the portfolio value against the invested amount, like the instrument chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const paths = document.querySelectorAll('[data-section=evolution] [data-chart] svg path');
                const value = [...paths].filter((path) => path.getAttribute('stroke') === '#4f46e5').length;
                const invested = [...paths]
                    .filter((path) => path.getAttribute('stroke') === '#94a3b8' && path.hasAttribute('stroke-dasharray'))
                    .length;
                return [value, invested].join('|');
            })()",
            '1|1',
        )
        ->assertNoJavaScriptErrors();
});

it('scales the value axis to the visible values instead of anchoring it at zero', function () {
    $this->actingAs(userWithEvolution());

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    expect(lowestValueAxisLabel($page, 'evolution'))->toBeGreaterThan(0.0);

    $page->assertNoJavaScriptErrors();
});

it('graduates the value axis in euros like the instrument chart, not on its bounds alone', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const texts = document.querySelectorAll('[data-section=evolution] [data-chart] svg text');
                const euros = [...texts].filter((text) => text.textContent.includes('€')).length;
                return euros >= 3 ? 'graduated' : 'only ' + euros;
            })()",
            'graduated',
        )
        ->assertNoJavaScriptErrors();
});

it('draws the value line without an area, like the instrument valuation chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "(() => {
                const paths = [...document.querySelectorAll('[data-section=evolution] [data-chart] svg path')];
                const line = paths.filter((path) => path.getAttribute('stroke') === '#4f46e5').length;
                const area = paths.filter((path) => path.getAttribute('fill') === '#4f46e5').length;
                return [line, area].join('|');
            })()",
            '1|0',
        )
        ->assertNoJavaScriptErrors();
});

it('stands as tall as the instrument valuation chart', function () {
    $this->actingAs(userWithEvolution());

    visit('/')
        ->assertScript(
            "getComputedStyle(document.querySelector('[data-section=evolution] [data-chart]')).height",
            '300px',
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
