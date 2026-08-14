<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * @param  array<string, float>  $sectors
 */
function holdingWithSectors(User $user, string $name, InstrumentType $type, float $close, array $sectors): void
{
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType($type)->create(['name' => $name]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 1,
        'avg_cost' => $close,
    ]);

    foreach ($sectors as $sector => $weight) {
        SectorAllocation::factory()->create([
            'asset_id' => $asset->id,
            'sector' => Sector::from($sector),
            'weight' => $weight,
        ]);
    }
}

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function soleUser(): User
{
    User::query()->delete();

    return User::factory()->create();
}

const SECTOR_LABELS = "Array.from(document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]')).map(el => el.textContent).join('|')";

it('renders one row per sector, sorted by decreasing weight', function () {
    $user = soleUser();

    holdingWithSectors($user, 'ACME ETF', InstrumentType::ETF, 1000.0, [
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);
    holdingWithSectors($user, 'Bitcoin', InstrumentType::Crypto, 500.0, []);

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Répartition sectorielle')
        ->assertScript(SECTOR_LABELS, 'Technologie|Autre|Santé')
        ->assertNoJavaScriptErrors();
});

it('scales the bars against the largest sector, not the total', function () {
    $user = soleUser();

    holdingWithSectors($user, 'ACME ETF', InstrumentType::ETF, 1000.0, [
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $this->actingAs($user);

    visit('/')
        ->assertScript(
            "document.querySelector('[data-section=\"sectors\"] [data-sector-bar]').style.width",
            '100%',
        )
        ->assertScript(
            "document.querySelector('[data-section=\"sectors\"] [data-sector-share]').textContent",
            '60,0 %',
        )
        ->assertNoJavaScriptErrors();
});

it('collapses the sectors past the sixth behind a toggle', function () {
    $user = soleUser();

    holdingWithSectors($user, 'ACME ETF', InstrumentType::ETF, 1000.0, [
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

    $page = visit('/');

    $page->assertScript(
        "document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]').length",
        6,
    )
        ->assertDontSee('Énergie')
        ->click('Voir les 2 autres')
        ->assertScript(
            "document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]').length",
            8,
        )
        ->assertSee('Énergie')
        ->assertSee('Immobilier')
        ->click('Réduire')
        ->assertScript(
            "document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]').length",
            6,
        )
        ->assertNoJavaScriptErrors();
});

it('keeps every sector visible when there are six or fewer', function () {
    $user = soleUser();

    holdingWithSectors($user, 'ACME ETF', InstrumentType::ETF, 1000.0, [
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $this->actingAs($user);

    visit('/')
        ->assertDontSee('Voir les')
        ->assertNoJavaScriptErrors();
});

it('shows an empty state when no holding has a market value', function () {
    $user = soleUser();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'ACME ETF']);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 1,
        'avg_cost' => 100,
    ]);

    $this->actingAs($user);

    visit('/')
        ->assertSee('Pas encore de données sectorielles.')
        ->assertNoJavaScriptErrors();
});
