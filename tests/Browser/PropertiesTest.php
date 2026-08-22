<?php

use App\Contexts\Identity\Models\User;

/**
 * Page du parc immobilier. `propertyFixture()` acquiert un bien 108 000 € frais compris, l'estime
 * 150 000 €, le loue 600 €/mois et lui passe 1 000 € de charges en janvier — le seul mois
 * déficitaire, qui porte donc l'investi à 108 400 €. Sans prêt, le patrimoine net vaut la valeur
 * estimée. Les espaces d'`Intl` sont des insécables fines, normalisées avant comparaison.
 */
$normalise = "el => el.textContent.replace(/\\s+/g, ' ').trim()";

it('porte le patrimoine net du parc en grand chiffre et le gain en pastille', function () use ($normalise) {
    ['user' => $user] = propertyFixture();

    $this->actingAs($user);

    visit('/properties')
        ->assertScript("({$normalise})(document.querySelector('[data-real-estate-net]'))", '150 000 €')
        ->assertScript("({$normalise})(document.querySelector('[data-real-estate-gain-pct]'))", '+38,4 %')
        ->assertNoJavaScriptErrors();
});

it('détaille investi, gain, estimé, restant dû et cash-flow sous le grand chiffre', function () use ($normalise) {
    ['user' => $user] = propertyFixture();

    $this->actingAs($user);

    visit('/properties')
        ->assertScript(
            "({$normalise})(document.querySelector('[data-real-estate-meta]'))",
            'Investi 108 400 € Gain +41 600 € Estimé 150 000 € Restant dû 0 € Cash-flow/mois +517 €',
        )
        ->assertNoJavaScriptErrors();
});

it('masque la pastille de gain quand le parc est vide', function () {
    $this->actingAs(User::factory()->create());

    visit('/properties')
        ->assertScript("document.querySelectorAll('[data-real-estate-gain-pct]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('trace le patrimoine net du parc dès que la série arrive', function () {
    ['user' => $user] = propertyFixture();

    $this->actingAs($user);

    visit('/properties')
        ->assertSee('Évolution')
        ->assertScript("document.querySelectorAll('[data-section=real-estate-evolution] [data-chart] svg').length", 1)
        ->assertNoJavaScriptErrors();
});
