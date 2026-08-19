<?php

/**
 * Chargement des deux pages de l'application : le squelette répond, les sections sont là dans
 * l'ordre attendu et rien n'explose côté JS. Les valeurs rendues dans ces sections sont vérifiées
 * par les tests dédiés à chaque composant (ValuationSectionTest, PerformanceBarsTest,
 * HeroSectionTest).
 */
it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|instruments|performances|income|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la fiche instrument et ses sections, sans erreur', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('ACME')
        ->assertSee('Transactions')
        ->assertSee('Répartition sectorielle')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|performance|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});
