<?php

/**
 * La conservation de la fenêtre entre les deux séries se vérifie au niveau du composant
 * (`InstrumentChart.test.ts`) : un déplacement de la mini-timeline ne se simule pas fidèlement en
 * DOM, et un test qui le croit passe même la mémorisation débranchée. Ici on vérifie ce que seul
 * un vrai navigateur montre : les deux séries se tracent, sans erreur au passage de l'une à l'autre.
 */
it('bascule le graphe de la fiche entre valorisation et cours', function () {
    ['user' => $user, 'instrument' => $instrument] = denseHistoryFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertVisible('[data-section="valuation"] [data-chart] svg')
        ->assertSeeIn('[data-section="valuation"]', 'Valorisation')
        ->assertSeeIn('[data-section="valuation"]', 'Cours')
        ->click('[data-segment="price"]')
        ->assertVisible('[data-section="valuation"] [data-chart] svg')
        ->assertNoJavaScriptErrors();
});
