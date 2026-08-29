<?php

/**
 * Chargement des trois pages de l'application : le squelette répond, les sections sont là dans
 * l'ordre attendu et rien n'explose côté JS. Les valeurs rendues dans ces sections sont vérifiées
 * par les tests dédiés à chaque composant (ValuationSectionTest, PerformanceBarsTest,
 * HeroSectionTest).
 */
it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Investi')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'wealth-summary|wealth-evolution|wealth-income',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Actions, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/actions')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|instruments|performances|income|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Crypto, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = cryptoFixture();

    $this->actingAs($user);

    visit('/crypto')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|instruments|performances',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Immobilier et ses sections, sans erreur', function () {
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit('/properties')
        ->assertSee('Biens')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'real-estate-summary|real-estate-evolution|real-estate|profitability|rental-income',
        )
        ->assertNoJavaScriptErrors();
});
