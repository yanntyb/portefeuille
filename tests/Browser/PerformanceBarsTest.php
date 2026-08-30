<?php

/**
 * La fixture ouvre la position le 1er janvier 2026 : la série couvre l'année en cours sans
 * atteindre un an plein, donc YTD, 1/3/6 mois et Max, jamais de ligne annuelle.
 *
 * Les largeurs et le libellé de survol sont calculés par `performanceBars()`, couvert par
 * `resources/js/lib/performance.test.ts` ; ce qui est vérifié ici, c'est leur arrivée dans le DOM.
 */
it('trace une barre par période servie', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-perf-label]')).map(el => el.textContent.trim()).join('|')",
            'YTD|1 mois|3 mois|6 mois|Max',
        )
        ->assertNoJavaScriptErrors();
});

it('mesure les barres contre la plus grande, qui occupe toute la largeur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $widths = "Array.from(document.querySelectorAll('[data-perf-bar]')).map(el => parseFloat(el.style.width))";

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertScript("Math.max(...{$widths})", 100)
        ->assertScript("{$widths}.every(width => width >= 0 && width <= 100)", true)
        ->assertNoJavaScriptErrors();
});

it('porte la valeur de début et les apports en libellé de survol', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-perf-row]')).every(el => /^Valeur début .+ · Apports [+-]?.+$/.test(el.title))",
            true,
        )
        ->assertNoJavaScriptErrors();
});

it('colore le gain et le pourcentage de chaque période', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $toned = "Array.from(document.querySelectorAll('[data-perf-gain]')).every(el =>
        /text-(gain|loss|muted-foreground)/.test(el.className))";

    visit('/actions')
        ->click('[data-section=analysis] [data-section-toggle]')
        ->assertScript("document.querySelectorAll('[data-perf-gain]').length", 5)
        ->assertScript("document.querySelectorAll('[data-perf-pct]').length", 5)
        ->assertScript($toned, true)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-perf-pct]')).every(el => /%$/.test(el.textContent.trim()))",
            true,
        )
        ->assertNoJavaScriptErrors();
});
