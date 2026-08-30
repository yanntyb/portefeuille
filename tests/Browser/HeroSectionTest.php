<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;

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

    visit("/asset/{$instrument->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]'))", '1 000,00 €')
        ->assertScript("({$normalise})(document.querySelector('[data-hero-gain-pct]'))", '+25,0 %')
        ->assertNoJavaScriptErrors();
});

it('colle investi et gain au grand chiffre, comme le tableau de bord', function () use ($normalise) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    /** Le résumé se lit entre la valeur et les repères du milieu de page. */
    $beneathValue = "(() => {
        const value = document.querySelector('[data-hero-value]').getBoundingClientRect();
        const summary = document.querySelector('[data-hero-summary]').getBoundingClientRect();
        const meta = document.querySelector('[data-hero-meta]').getBoundingClientRect();

        return summary.top >= value.bottom - 1 && summary.bottom <= meta.top + 1;
    })()";

    visit("/asset/{$instrument->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-summary]'))", 'Investi 800,00 € Gain +200,00 €')
        ->assertScript($beneathValue, true)
        ->assertNoJavaScriptErrors();
});

it('détaille le seul prix de revient, le cours se lisant sur la courbe', function () use ($metaEntries) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertScript($metaEntries, 'PRU 80,00 €')
        ->assertNoJavaScriptErrors();
});

it('remplace le détail par la date du dernier cours quand le titre n\'est pas détenu', function () use ($normalise, $metaEntries) {
    ['user' => $user] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'ORPHAN', 'ticker' => 'ORP']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => '2026-07-01', 'close' => 42]);

    $this->actingAs($user);

    visit("/asset/{$other->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]'))", '42,00 €')
        ->assertScript($metaEntries, 'au 01/07/2026')
        ->assertScript("document.querySelectorAll('[data-hero-summary]').length", 0)
        ->assertScript("document.querySelectorAll('[data-hero-gain-pct]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('sépare gain latent et gain réalisé dès qu\'une vente est passée', function () use ($normalise) {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    // Deux titres revendus 100 € pour un prix de revient de 80 € : +40 € encaissés. La position
    // retombe à huit titres, soit 640 € investis et +160 € de latent.
    Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 2,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2026-02-01',
    ]);

    $this->actingAs($user);

    /** Les deux montants vivent dans deux spans : l'écart est un `gap`, pas un blanc du texte. */
    visit("/asset/{$instrument->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-gain]'))", 'Gain +160,00 € (latent)+40,00 € (réalisé)')
        ->assertScript("({$normalise})(document.querySelector('[data-realized-gain]'))", '+40,00 € (réalisé)')
        ->assertNoJavaScriptErrors();
});

it('garde le seul repère « Gain » sur un portefeuille sans vente', function () use ($normalise) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-summary]'))", 'Investi 800,00 € Gain +200,00 €')
        ->assertScript("document.querySelectorAll('[data-realized-gain]').length", 0)
        ->assertNoJavaScriptErrors();
});
