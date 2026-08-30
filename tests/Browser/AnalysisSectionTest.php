<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;

it('déroule les repères d\'analyse d\'une position', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertSee('PRU')
        ->assertSee('Poids du portefeuille')
        ->assertScript("document.querySelectorAll('[data-analysis-row]').length > 0", true)
        ->assertNoJavaScriptErrors();
});

it('n\'offre pas d\'analyse sur un titre qui n\'est pas détenu', function () {
    ['user' => $user] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'ORPHAN', 'ticker' => 'ORP']);
    Price::factory()->create([
        'asset_id' => $other->id,
        'date' => '2026-07-01',
        'close' => 42,
    ]);

    $this->actingAs($user);

    visit("/asset/{$other->id}")
        ->assertScript("document.querySelectorAll('[data-section=\"analysis\"]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('finit l\'analyse d\'une position par ses performances', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertSee('Performances')
        ->assertScript("document.querySelectorAll('[data-section=analysis] [data-perf-row]').length > 0", true)
        ->assertScript(
            "!!document.querySelector('[data-perf-help] [aria-label=\"Comment lire les performances par période\"]')",
            true,
        )
        ->assertNoJavaScriptErrors();
});
