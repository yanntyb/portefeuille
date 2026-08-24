<?php

/**
 * Page crypto. `cryptoFixture()` tient un bitcoin à 400 €, payé 300 €, à côté d'un portefeuille de
 * dix titres à 100 €. Les espaces d'`Intl` sont des insécables fines, normalisées.
 */
$normalise = "el => el.textContent.replace(/\\s+/g, ' ').trim()";

it('ne valorise que la crypto sur sa page', function () use ($normalise) {
    ['user' => $user] = cryptoFixture();

    $this->actingAs($user);

    visit('/crypto')
        ->assertScript("({$normalise})(document.querySelector('[data-portfolio-value]'))", '400 €')
        ->assertScript("({$normalise})(document.querySelector('[data-instrument-name]'))", 'Bitcoin (BTC-EUR)')
        ->assertNoJavaScriptErrors();
});

it('laisse la crypto hors de la page Actions', function () use ($normalise) {
    ['user' => $user] = cryptoFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertScript("({$normalise})(document.querySelector('[data-portfolio-value]'))", '1 000 €')
        ->assertScript("document.body.textContent.includes('Bitcoin')", false)
        ->assertNoJavaScriptErrors();
});

it('mène de la liste crypto à la fiche de la crypto', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $this->actingAs($user);

    visit('/crypto')
        ->click('[data-instrument-name]')
        ->assertScript('location.pathname', "/asset/{$bitcoin->id}")
        ->assertSee('Bitcoin')
        ->assertNoJavaScriptErrors();
});

it('mène du tableau de bord à la page crypto', function () {
    ['user' => $user] = cryptoFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSeeIn('[data-section="wealth-summary"]', 'Crypto')
        ->click('a[href="/crypto"]')
        ->assertSee('Performances')
        ->assertNoJavaScriptErrors();
});
