<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Seeds an instrument whose price moves from `open` to `close` over the last ten days.
 */
function seedCatalogInstrument(string $name, float $open, float $close, InstrumentType $type = InstrumentType::ETF): Instrument
{
    $asset = Instrument::factory()->ofType($type)->create(['name' => $name, 'ticker' => strtoupper(substr($name, 0, 3))]);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10)->format('Y-m-d'), 'close' => $open]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->format('Y-m-d'), 'close' => $close]);

    return $asset;
}

/**
 * A legacy data migration seeds a hardcoded user; clear it so the controller resolves the test user.
 */
function userWithCatalog(): User
{
    User::query()->delete();

    return User::factory()->create();
}

function textOfCatalog(string $selector): string
{
    return "Array.from(document.querySelectorAll('{$selector}')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";
}

it('sums up the period with the best riser, the worst faller and the share going up', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Beta', 100, 80);
    seedCatalogInstrument('Gamma', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha')
        ->assertScript(textOfCatalog('[data-catalog-best]'), 'Alpha +50,0 %')
        ->assertScript(textOfCatalog('[data-catalog-worst]'), 'Beta -20,0 %')
        ->assertScript(textOfCatalog('[data-catalog-worst-label]'), 'Pire baisse')
        ->assertScript(textOfCatalog('[data-catalog-up]'), '2 / 3')
        ->assertNoJavaScriptErrors();
});

it('renames the laggard when no instrument is down over the period', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Gamma', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha')
        ->assertScript(textOfCatalog('[data-catalog-worst-label]'), 'Plus faible hausse')
        ->assertScript(textOfCatalog('[data-catalog-worst]'), 'Gamma +5,0 %')
        ->assertScript(textOfCatalog('[data-catalog-up]'), '2 / 2')
        ->assertNoJavaScriptErrors();
});

it('lists every instrument alphabetically with its trend, price and change', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Gamma', 100, 105);
    seedCatalogInstrument('Alpha', 100, 150);

    visit('/instruments')
        ->assertSee('Alpha')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 2)
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Alpha (ALP)|Gamma (GAM)')
        ->assertScript(textOfCatalog('[data-catalog-change]'), '+50,0 %|+5,0 %')
        ->assertScript(textOfCatalog('[data-catalog-price]'), '150,00 €|105,00 €')
        ->assertScript("document.querySelectorAll('[data-catalog-row] [data-sparkline]').length", 2)
        ->assertNoJavaScriptErrors();
});

it('reorders the list when a column header is clicked', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Beta', 100, 80);
    seedCatalogInstrument('Gamma', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha')
        ->click('[data-sort=change]')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Alpha (ALP)|Gamma (GAM)|Beta (BET)')
        ->click('[data-sort=change]')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Beta (BET)|Gamma (GAM)|Alpha (ALP)')
        ->assertNoJavaScriptErrors();
});

it('marks the held instruments apart from the watched ones', function () {
    $user = userWithCatalog();
    $wallet = Wallet::factory()->for($user)->create();
    $held = seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Beta', 100, 80);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $held->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    visit('/instruments')
        ->assertSee('Alpha')
        ->assertScript("document.querySelectorAll('[data-catalog-row][data-held=true]').length", 1)
        ->assertScript(textOfCatalog('[data-catalog-value]'), '1 500 €|—')
        ->assertNoJavaScriptErrors();
});
