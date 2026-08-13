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

it('renders one horizontal bar per sector, sorted by decreasing weight', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();

    holdingWithSectors($user, 'ACME ETF', InstrumentType::ETF, 1000.0, [
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);
    holdingWithSectors($user, 'Bitcoin', InstrumentType::Crypto, 500.0, []);

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Répartition sectorielle')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=\"sectors\"] .apexcharts-yaxis-texts-g text tspan')).map(el => el.textContent).join('|')",
            'Technologie|Autre|Santé',
        )
        ->assertScript(
            "document.querySelectorAll('[data-section=\"sectors\"] .apexcharts-bar-area').length",
            3,
        )
        ->assertNoJavaScriptErrors();
});

it('shows an empty state when no holding has a market value', function () {
    User::query()->delete();
    $user = User::factory()->create();
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
