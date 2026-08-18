<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/** Un instrument dont le cours passe de `open` à `close` sur les dix derniers jours. */
function seedCatalogInstrument(string $name, float $open, float $close): Instrument
{
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create([
        'name' => $name,
        'ticker' => strtoupper(substr($name, 0, 3)),
    ]);

    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->subDays(10)->format('Y-m-d'),
        'close' => $open,
    ]);
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->format('Y-m-d'),
        'close' => $close,
    ]);

    return $instrument;
}

it('groupe positions et catalogue sans recherche, aplatit les résultats dès la première frappe, et revient', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $held = seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Bravo Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $held->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    $names = "Array.from(document.querySelectorAll('[data-instrument-name]')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";
    $groups = "Array.from(document.querySelectorAll('[data-instrument-group]')).map(el => el.textContent.trim()).join('|')";

    visit('/')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 3)
        ->assertScript($groups, 'Mes positions|Autres instruments')
        ->assertScript($names, 'Alpha Fund (ALP)|Bravo Fund (BRA)|Gamma Trust (GAM)')
        ->type('[data-instrument-search]', 'fund')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 2)
        ->assertScript("document.querySelectorAll('[data-instrument-group]').length", 0)
        ->assertScript($names, 'Alpha Fund (ALP)|Bravo Fund (BRA)')
        ->assertScript("document.querySelector('[data-instrument-row]').getAttribute('data-held')", 'true')
        ->assertScript("document.querySelectorAll('[data-instrument-held-badge]').length", 1)
        ->type('[data-instrument-search]', 'zzz')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 0)
        ->assertScript(
            "document.querySelector('[data-instrument-empty]').textContent.replace(/\\s+/g, ' ').trim()",
            'Aucun instrument ne correspond à cette recherche.',
        )
        ->click('[data-instrument-search-clear]')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 3)
        ->assertScript($groups, 'Mes positions|Autres instruments')
        ->assertNoJavaScriptErrors();
});

it('classe les résultats par pertinence, le ticker tapé avant le nom qui commence pareil', function () {
    ['user' => $user] = portfolioFixture();

    /** « Zeta » commence son nom, « ZET » est le ticker de l'autre : le ticker doit passer devant. */
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'Zeta Trust', 'ticker' => 'TRU']);
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'Delta Fund', 'ticker' => 'ZET']);

    $this->actingAs($user);

    $names = "Array.from(document.querySelectorAll('[data-instrument-name]')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit('/')
        ->type('[data-instrument-search]', 'zet')
        ->assertScript($names, 'Delta Fund (ZET)|Zeta Trust (TRU)')
        ->assertNoJavaScriptErrors();
});

it('montre un instrument non détenu sans recherche, sous le groupe du catalogue', function () {
    ['user' => $user] = portfolioFixture();

    seedCatalogInstrument('Zeta Trust', 100, 120);

    $this->actingAs($user);

    visit('/')
        ->assertSee('Autres instruments')
        ->assertSee('Zeta Trust')
        ->type('[data-instrument-search]', 'zeta')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 1)
        ->assertScript("document.querySelector('[data-instrument-row]').getAttribute('data-held')", 'false')
        ->assertScript("document.querySelectorAll('[data-instrument-held-badge]').length", 0)
        ->assertNoJavaScriptErrors();
});
