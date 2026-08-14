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
function seedCatalogInstrument(string $name, float $open, float $close, InstrumentType $type = InstrumentType::ETF, ?string $isin = null, ?string $ticker = null): Instrument
{
    $asset = Instrument::factory()->ofType($type)->create([
        'name' => $name,
        'ticker' => $ticker ?? strtoupper(substr($name, 0, 3)),
        'isin' => $isin,
    ]);

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

it('narrows the list to the instruments matching the name or the ticker', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Beta', 100, 80, InstrumentType::ETF, null, 'XYZW');
    seedCatalogInstrument('Gamma', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha')
        ->type('[data-catalog-search]', 'amm')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Gamma (GAM)')
        ->type('[data-catalog-search]', 'xyz')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Beta (XYZW)')
        ->assertNoJavaScriptErrors();
});

it('searches on the isin and ignores the accents', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Société Générale', 100, 150, InstrumentType::Stock, 'FR0000130809');
    seedCatalogInstrument('Alpha', 100, 105, InstrumentType::ETF, 'IE00B4L5Y983');

    visit('/instruments')
        ->assertSee('Alpha')
        ->type('[data-catalog-search]', 'fr00001308')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Société Générale (SOC)')
        ->type('[data-catalog-search]', 'societe gen')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Société Générale (SOC)')
        ->assertNoJavaScriptErrors();
});

it('recomputes the header stats on the searched instruments', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Beta Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha Fund')
        ->assertScript(textOfCatalog('[data-catalog-count]'), '3 instruments')
        ->type('[data-catalog-search]', 'fund')
        ->assertScript(textOfCatalog('[data-catalog-count]'), '2 instruments')
        ->assertScript(textOfCatalog('[data-catalog-best]'), 'Alpha Fund +50,0 %')
        ->assertScript(textOfCatalog('[data-catalog-worst]'), 'Beta Fund -20,0 %')
        ->assertScript(textOfCatalog('[data-catalog-up]'), '1 / 2')
        ->assertNoJavaScriptErrors();
});

it('tells the search came back empty and restores the list once cleared', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha', 100, 150);
    seedCatalogInstrument('Beta', 100, 80);

    visit('/instruments')
        ->assertSee('Alpha')
        ->type('[data-catalog-search]', 'zzz')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 0)
        ->assertScript(textOfCatalog('[data-catalog-empty]'), 'Aucun instrument ne correspond à cette recherche.')
        ->click('[data-catalog-search-clear]')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Alpha (ALP)|Beta (BET)')
        ->assertNoJavaScriptErrors();
});

it('keeps the column sort working on the searched instruments', function () {
    $this->actingAs(userWithCatalog());

    seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Bravo Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    visit('/instruments')
        ->assertSee('Alpha Fund')
        ->type('[data-catalog-search]', 'fund')
        ->click('[data-sort=change]')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Alpha Fund (ALP)|Bravo Fund (BRA)')
        ->click('[data-sort=change]')
        ->assertScript(textOfCatalog('[data-catalog-name]'), 'Bravo Fund (BRA)|Alpha Fund (ALP)')
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
