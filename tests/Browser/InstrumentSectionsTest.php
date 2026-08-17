<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function instrumentSheet(bool $held): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    SectorAllocation::factory()->create([
        'asset_id' => $asset->id,
        'sector' => Sector::Technology,
        'weight' => 1.0,
    ]);

    if ($held) {
        $wallet = Wallet::factory()->for($user)->create();
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'avg_cost' => 80,
        ]);
    }

    return ['user' => $user, 'instrument' => $asset];
}

it('splits the held instrument sheet into sections inside a single card', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentSheet(held: true);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertSee('ACME ETF')
        ->assertSee('Transactions')
        ->assertSee('Répartition sectorielle')
        ->assertScript("document.querySelectorAll('main [data-slot=card]').length", 1)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});

it('scales the price axis to the visible quotes instead of anchoring it at zero', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentSheet(held: false);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-08', 'close' => 90]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-15', 'close' => 110]);

    $this->actingAs($user);

    $page = visit("/instruments/{$asset->id}");
    $page->assertScript("document.querySelector('[data-section=price-history] [data-chart] svg') !== null", true);

    expect(lowestValueAxisLabel($page, 'price-history'))->toBeGreaterThan(0.0);

    $page->assertNoJavaScriptErrors();
});

it('swaps the valuation section for the price history when the instrument is not held', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentSheet(held: false);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('main [data-slot=card]').length", 1)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|price-history|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});
