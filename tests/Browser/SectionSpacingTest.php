<?php

/**
 * Les titres de section se suivent à intervalle constant : c'est le pas de la page. Un slot
 * `aside` — le bouton d'aide des performances — est plus haut qu'un titre, et sans garde il
 * pousserait ses deux voisines d'un cran, seule la sienne paraissant plus espacée.
 */
it('garde le même pas entre toutes les sections repliées', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertScript(
            "new Set(Array.from(document.querySelectorAll('[data-section]'))
                .filter(el => el.dataset.section !== 'valuation' && el.dataset.section !== 'evolution')
                .map(el => Math.round(el.getBoundingClientRect().height))).size",
            1,
        )
        ->assertNoJavaScriptErrors();
});
