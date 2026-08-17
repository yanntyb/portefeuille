<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;

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

it('cherche, trie les résultats sur une colonne, puis restitue la liste une fois effacée', function () {
    $user = User::factory()->create();

    seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Bravo Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    $this->actingAs($user);

    $names = "Array.from(document.querySelectorAll('[data-catalog-name]')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit('/instruments')
        ->assertSee('Alpha Fund')
        ->type('[data-catalog-search]', 'fund')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 2)
        ->click('[data-sort=change]')
        ->assertScript($names, 'Alpha Fund (ALP)|Bravo Fund (BRA)')
        ->click('[data-sort=change]')
        ->assertScript($names, 'Bravo Fund (BRA)|Alpha Fund (ALP)')
        ->type('[data-catalog-search]', 'zzz')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 0)
        ->assertScript(
            "document.querySelector('[data-catalog-empty]').textContent.replace(/\\s+/g, ' ').trim()",
            'Aucun instrument ne correspond à cette recherche.',
        )
        ->click('[data-catalog-search-clear]')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 3)
        ->assertNoJavaScriptErrors();
});
