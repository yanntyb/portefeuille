<?php

use App\Contexts\RealEstate\Models\RentException;

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
            'hero|metrics|loan|income',
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

    /** Libellé et valeur sont sur deux lignes : les recoller pour lire chaque repère d'un bloc. */
    $summary = "Array.from(document.querySelectorAll('[data-loan-summary] > span'))
        .map(el => Array.from(el.children).map(child => child.textContent.replace(/\\s+/g, ' ').trim()).join(' '))
        .join('|')";

    visit("/properties/{$property->id}")
        ->assertSee('Crédit')
        ->assertScript("({$summary}).includes('Payé 20/240 mois')", true)
        ->assertScript("({$summary}).includes('Mensualité 333,33 €')", true)
        ->assertScript("({$summary}).includes('Intérêts à venir 0,00 €')", true)
        ->assertNoJavaScriptErrors();
});

it('pose chaque montant du prêt sous son libellé sans déborder de l\'écran', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    /** Le montant commence sous son libellé et finit au bord droit du repère. */
    $stacked = "Array.from(document.querySelectorAll('[data-loan-summary] > span')).every(entry => {
        if (entry.children.length !== 2) {
            return false;
        }

        const label = entry.children[0].getBoundingClientRect();
        const value = entry.children[1].getBoundingClientRect();

        return value.top >= label.bottom && Math.abs(value.right - entry.getBoundingClientRect().right) <= 1;
    })";

    /** Le débordement se joue dans la grille du résumé, pas au niveau du document : il y est masqué. */
    $fits = "(() => {
        const summary = document.querySelector('[data-loan-summary]');

        return summary.scrollWidth <= summary.clientWidth;
    })()";

    visit("/properties/{$property->id}")->on()->iPhone14Pro()
        ->assertScript($fits, true)
        ->assertScript($stacked, true)
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

it('réunit loyers, charges et crédit en une liste, l\'année la plus récente ouverte', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    $currentYear = now()->year;

    visit("/properties/{$property->id}")
        ->assertSee('Revenus & charges')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-income-year]')).map(el => el.dataset.incomeYear).join('|')
                === Array.from(document.querySelectorAll('[data-income-year]')).map(el => el.dataset.incomeYear).sort().reverse().join('|')",
            true,
        )
        ->assertScript("document.querySelectorAll('[data-income-month]').length > 0", true)
        ->click("[data-income-year=\"{$currentYear}\"]")
        ->assertScript("document.querySelectorAll('[data-income-month]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('détaille loyer, charges et crédit derrière un clic sur un mois', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    $thisMonth = now()->startOfMonth()->toDateString();

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-income-detail]').length", 0)
        ->click("[data-income-month=\"{$thisMonth}\"]")
        ->assertScript("document.querySelectorAll('[data-income-detail]').length", 1)
        ->assertScript("document.querySelector('[data-income-detail]').textContent.includes('600,00')", true)
        ->assertNoJavaScriptErrors();
});

it('garde l\'année précédente repliée dans la liste', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    /** Vingt mois de bail : l'année précédente est toujours présente, et repliée. */
    $previousYear = now()->subYear()->year;

    visit("/properties/{$property->id}")
        ->assertScript("document.querySelectorAll('[data-income-year]').length >= 2", true)
        ->assertScript(
            "document.querySelectorAll('[data-income-month]').length < document.querySelectorAll('[data-income-year]').length * 12",
            true,
        )
        ->click("[data-income-year=\"{$previousYear}\"]")
        ->assertScript("document.querySelectorAll('[data-income-month]').length >= 12", true)
        ->assertNoJavaScriptErrors();
});

it('signale un loyer impayé sur la ligne de son mois', function () use ($normalise) {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $month = now()->startOfMonth()->toDateString();

    RentException::factory()->create([
        'lease_id' => $property->leases()->first()->id,
        'month' => $month,
        'amount_override' => 0,
        'note' => 'Impayé',
    ]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertScript(
            "({$normalise})(document.querySelector('[data-income-month=\"{$month}\"] [data-income-status]'))",
            'impayé',
        )
        ->assertNoJavaScriptErrors();
});

it('ventile les charges de l\'année en pied de groupe, la plus grosse en tête', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertSee('Charges')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section=\"income\"] [data-sector-label]')).map(el => el.textContent.trim()).join('|')",
            'Travaux|Taxe foncière',
        )
        ->assertScript("document.querySelectorAll('[data-section=\"income\"] [data-sector-bar]').length", 2)
        ->assertNoJavaScriptErrors();
});
