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
 *
 * @param  array<string, float>  $sectors
 */
function instrumentWithSectors(array $sectors): array
{
    User::query()->delete();
    $user = User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'ACME ETF']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    foreach ($sectors as $sector => $weight) {
        SectorAllocation::factory()->create([
            'asset_id' => $asset->id,
            'sector' => Sector::from($sector),
            'weight' => $weight,
        ]);
    }

    return ['user' => $user, 'instrument' => $asset];
}

it('lists one row per sector, sorted by decreasing weight', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithSectors([
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-sector-label]')).map(el => el.textContent).join('|')",
            'Technologie|Santé',
        )
        ->assertScript("document.querySelector('[data-sector-bar]').style.width", '100%')
        ->assertScript("document.querySelector('[data-sector-share]').textContent", '60,0 %')
        ->assertNoJavaScriptErrors();
});

it('collapses the sectors past the sixth behind a toggle', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithSectors([
        Sector::Technology->value => 0.3,
        Sector::Healthcare->value => 0.2,
        Sector::FinancialServices->value => 0.15,
        Sector::CommunicationServices->value => 0.12,
        Sector::ConsumerCyclical->value => 0.1,
        Sector::Industrials->value => 0.07,
        Sector::Energy->value => 0.04,
        Sector::RealEstate->value => 0.02,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-sector-label]').length", 6)
        ->assertDontSee('Énergie')
        ->click('Voir les 2 autres')
        ->assertScript("document.querySelectorAll('[data-sector-label]').length", 8)
        ->assertSee('Énergie')
        ->assertSee('Immobilier')
        ->click('Réduire')
        ->assertScript("document.querySelectorAll('[data-sector-label]').length", 6)
        ->assertNoJavaScriptErrors();
});

it('breaks the position value down per sector when the instrument is held', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithSectors([
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $wallet = Wallet::factory()->for($user)->create();
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-sector-amount]')).map(el => el.textContent.replace(/\\s/g, ' ')).join('|')",
            '600 €|400 €',
        )
        ->assertNoJavaScriptErrors();
});

it('shows the share alone when the instrument is not held', function () {
    ['user' => $user, 'instrument' => $asset] = instrumentWithSectors([
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$asset->id}")
        ->assertScript("document.querySelectorAll('[data-sector-amount]').length", 0)
        ->assertScript("document.querySelectorAll('[data-sector-share]').length", 2)
        ->assertNoJavaScriptErrors();
});
