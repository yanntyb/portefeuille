<?php

/**
 * Fiche d'un bien : `propertyFixture()` l'acquiert 108 000 € frais compris, l'estime 150 000 € et
 * le loue 600 €/mois. Sans prêt, le patrimoine net vaut donc la valeur estimée, et la plus-value
 * 42 000 €, soit +38,9 %. Les espaces d'`Intl` sont des insécables fines, normalisées.
 */
$normalise = "el => el.textContent.replace(/\\s+/g, ' ').trim()";

$metaEntries = "Array.from(document.querySelectorAll('[data-hero-meta] > span'))
    .map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

it('porte le patrimoine net en grand chiffre et la plus-value en pastille', function () use ($normalise) {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]'))", '150 000,00 €')
        ->assertScript("({$normalise})(document.querySelector('[data-hero-gain-pct]'))", '+38,9 %')
        ->assertNoJavaScriptErrors();
});

it('détaille valeur estimée, restant dû, investi et cash-flow mensuel', function () use ($metaEntries) {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript(
            "({$metaEntries}).startsWith('Valeur estimée 150 000,00 €|Restant dû 0,00 €|Investi 108 000,00 €|Cash-flow/mois ')",
            true,
        )
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 1)
        ->assertNoJavaScriptErrors();
});

it('retranche le capital restant dû du patrimoine net quand le bien est financé', function () use ($normalise, $metaEntries) {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]')) !== '150 000,00 €'", true)
        ->assertScript("({$metaEntries}).includes('Restant dû 0,00 €')", false)
        ->assertNoJavaScriptErrors();
});

it('ordonne les sections comme la fiche d\'un instrument, le graphe sous l\'en-tête', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|metrics|cash-flow|rents|expenses|amortization',
        )
        ->assertNoJavaScriptErrors();
});

it('replie le cash-flow par année et ouvre la plus récente', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    $currentYear = now()->year;

    visit("/properties/{$property->id}")
        ->assertSee('Cash-flow mensuel')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-cash-flow-year]')).map(el => el.dataset.cashFlowYear).join('|')
                === Array.from(document.querySelectorAll('[data-cash-flow-year]')).map(el => el.dataset.cashFlowYear).sort().reverse().join('|')",
            true,
        )
        ->assertScript("document.querySelectorAll('[data-cash-flow-month]').length > 0", true)
        ->click("[data-cash-flow-year=\"{$currentYear}\"]")
        ->assertScript("document.querySelectorAll('[data-cash-flow-month]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('détaille loyers, charges et crédit derrière un clic sur un mois de cash-flow', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    $thisMonth = now()->startOfMonth()->toDateString();

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-cash-flow-detail]').length", 0)
        ->click("[data-cash-flow-month=\"{$thisMonth}\"]")
        ->assertScript("document.querySelectorAll('[data-cash-flow-detail]').length", 1)
        ->assertScript("document.querySelector('[data-cash-flow-detail]').textContent.includes('600,00')", true)
        ->assertNoJavaScriptErrors();
});

it('groupe les loyers par année, la précédente repliée', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    /** Vingt mois de bail : l'année précédente est toujours présente, et repliée. */
    $previousYear = now()->subYear()->year;

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-rent-year]').length >= 2", true)
        ->assertScript(
            "document.querySelectorAll('[data-rent-month]').length < document.querySelectorAll('[data-rent-year]').length * 12",
            true,
        )
        ->click("[data-rent-year=\"{$previousYear}\"]")
        ->assertScript("document.querySelectorAll('[data-rent-month]').length >= 12", true)
        ->assertNoJavaScriptErrors();
});

it('ventile les charges de l\'année en barres, la plus grosse en tête', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertSee('Charges')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=\"expenses\"] [data-sector-label]')).map(el => el.textContent.trim()).join('|')",
            'Travaux|Taxe foncière',
        )
        ->assertScript("document.querySelectorAll('[data-section=\"expenses\"] [data-sector-bar]').length", 2)
        ->assertNoJavaScriptErrors();
});
