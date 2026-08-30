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

it('explique l\'ATR à travers son dialogue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->click('[aria-label="Comment lire l\'ATR"]')
        ->assertSee('Comment lire l\'ATR')
        ->assertSee('De combien bouge une journée ordinaire')
        ->assertNoJavaScriptErrors();
});

it('ne pose un bouton d\'aide que sur l\'ATR', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    /** Les autres repères se lisent dans leur libellé : une icône par ligne encombrait la section. */
    visit("/asset/{$instrument->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-analysis-row] button')).map(button => button.getAttribute('aria-label')).join('|')",
            'Comment lire l\'ATR',
        )
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
