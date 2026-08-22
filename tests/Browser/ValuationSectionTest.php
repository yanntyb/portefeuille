<?php

use App\Contexts\Identity\Models\User;

/**
 * Fixture : 10 titres à 100 €, prix de revient 80 €. Valeur 1 000 €, investi 800 €,
 * gain +200 €, soit +25,0 %. Les espaces rendues par `Intl` sont des insécables fines,
 * normalisées avant comparaison.
 */
$normalise = "el => el.textContent.replace(/\\s+/g, ' ').trim()";

it('rend la valeur du portefeuille', function () use ($normalise) {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertScript("({$normalise})(document.querySelector('[data-portfolio-value]'))", '1 000 €')
        ->assertNoJavaScriptErrors();
});

it('détaille le montant investi et le gain sous la valeur', function () use ($normalise) {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertScript("({$normalise})(document.querySelector('[data-portfolio-meta]'))", 'Investi 800 € Gain +200 €')
        ->assertNoJavaScriptErrors();
});

it('affiche le gain en pourcentage dans la pastille', function () use ($normalise) {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertScript("({$normalise})(document.querySelector('[data-portfolio-gain-pct]'))", '+25,0 %')
        ->assertNoJavaScriptErrors();
});

it('masque la section quand le portefeuille est vide', function () {
    $this->actingAs(User::factory()->create());

    visit('/instruments')
        ->assertScript("document.querySelectorAll('[data-section=valuation]').length", 0)
        ->assertNoJavaScriptErrors();
});
