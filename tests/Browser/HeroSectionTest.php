<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;

/**
 * Fixture : 10 titres à 100 €, prix de revient 80 €. La position vaut 1 000 €, investi 800 €,
 * gain +200 €, soit +25,0 %. Les espaces d'`Intl` sont des insécables fines, normalisées.
 */
$normalise = "el => el.textContent.replace(/\\s+/g, ' ').trim()";

$metaEntries = "Array.from(document.querySelectorAll('[data-hero-meta] > span'))
    .map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

it('affiche la valeur de la position détenue', function () use ($normalise) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]'))", '1 000,00 €')
        ->assertScript("({$normalise})(document.querySelector('[data-hero-gain-pct]'))", '+25,0 %')
        ->assertNoJavaScriptErrors();
});

it('détaille investi, gain, cours et prix de revient', function () use ($metaEntries) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript($metaEntries, 'Investi 800,00 €|Gain +200,00 €|Cours 100,00 €|PRU 80,00 €')
        ->assertNoJavaScriptErrors();
});

it('marque la seule ligne de gain, imbriquée dans le méta', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 1)
        ->assertScript(
            "document.querySelector('[data-hero-meta]').contains(document.querySelector('[data-hero-gain]'))",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('remplace le détail par la date du dernier cours quand le titre n\'est pas détenu', function () use ($normalise, $metaEntries) {
    ['user' => $user] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'ORPHAN', 'ticker' => 'ORP']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2026-07-01', 'close' => 42]);

    $this->actingAs($user);

    visit("/instruments/{$other->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]'))", '42,00 €')
        ->assertScript($metaEntries, 'au 01/07/2026')
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 0)
        ->assertScript("document.querySelectorAll('[data-hero-gain-pct]').length", 0)
        ->assertNoJavaScriptErrors();
});
