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

it('détaille investi et cash-flow mensuel, ce que la barre patrimoine ne dit pas', function () use ($metaEntries) {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript("({$metaEntries}).startsWith('Investi 108 000,00 €|Cash-flow/mois ')", true)
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 1)
        ->assertNoJavaScriptErrors();
});

it('retranche le capital restant dû du patrimoine net quand le bien est financé', function () use ($normalise) {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript("({$normalise})(document.querySelector('[data-hero-value]')) !== '150 000,00 €'", true)
        ->assertNoJavaScriptErrors();
});

it('découpe la valeur estimée entre le propriétaire et la banque', function () use ($normalise) {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    // 150 000 € de valeur pour 73 333 € restant dû après vingt échéances : la moitié du bien est
    // déjà à soi, et la barre le montre avant tout chiffre.
    visit("/properties/{$property->id}")
        ->assertScript("Math.round(parseFloat(document.querySelector('[data-equity-share]').style.width))", 51)
        ->assertScript(
            "({$normalise})(document.querySelector('[data-equity-legend]'))",
            'à moi 76 667 € banque 73 333 €',
        )
        ->assertNoJavaScriptErrors();
});

it('n\'affiche aucune barre patrimoine sur un bien sans prêt : rien à partager', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-equity-bar]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('ordonne les sections : les indicateurs sous l\'en-tête, le crédit ensuite', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|metrics|loan|cash-flow|rents|expenses',
        )
        ->assertNoJavaScriptErrors();
});

it('présente les indicateurs en tuiles, la valeur avant son libellé', function () use ($normalise) {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    $tiles = "Array.from(document.querySelectorAll('[data-metric]')).map(el => el.dataset.metric).join('|')";

    visit("/properties/{$property->id}")
        ->assertScript($tiles, 'grossYield|netYield|annualCashFlow|cashOnCash|ltv')
        // La valeur porte la tuile, le libellé la nomme dessous : l'œil balaie une ligne de chiffres.
        ->assertScript("document.querySelector('[data-metric=\"ltv\"]').firstElementChild.hasAttribute('data-metric-value')", true)
        ->assertScript("({$normalise})(document.querySelector('[data-metric=\"annualCashFlow\"] [data-metric-label]'))", 'Cash-flow annuel')
        ->assertNoJavaScriptErrors();
});

it('situe le prêt : échéances réglées, capital remboursé, intérêts', function () {
    /** Prêt de la fixture : 80 000 € sur 240 mois à 0 %, démarré il y a vingt mois. */
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    $summary = "Array.from(document.querySelectorAll('[data-loan-summary] > span'))
        .map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit("/properties/{$property->id}")
        ->assertSee('Crédit')
        ->assertScript("({$summary}).includes('Payé 20/240 mois')", true)
        ->assertScript("({$summary}).includes('Mensualité 333,33 €')", true)
        ->assertScript("({$summary}).includes('Intérêts à venir 0,00 €')", true)
        ->assertNoJavaScriptErrors();
});

it('replie l\'échéancier par année et ouvre celle en cours', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    $currentYear = now()->year;
    $thisMonth = now()->startOfMonth()->toDateString();

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-loan-year]').length >= 2", true)
        ->assertScript("document.querySelectorAll('[data-loan-month]').length > 0", true)
        ->assertScript("document.querySelectorAll('[data-loan-month=\"{$thisMonth}\"]').length", 1)
        ->click("[data-loan-year=\"{$currentYear}\"]")
        ->assertScript("document.querySelectorAll('[data-loan-month]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('cumule les années à venir en une ligne, dépliable année par année', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertSee('À venir')
        ->assertScript("document.querySelectorAll('[data-loan-future-year]').length", 0)
        ->click('[data-loan-future]')
        ->assertScript("document.querySelectorAll('[data-loan-future-year]').length >= 18", true)
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
