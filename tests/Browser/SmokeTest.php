<?php

it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|performances|holdings|sectors',
        )
        ->assertScript("document.querySelector('[data-portfolio-value]').textContent.trim() !== ''", true)
        ->assertScript("document.querySelector('[data-portfolio-meta]').textContent.trim() !== ''", true)
        ->assertScript(
            "document.querySelector('[data-section=evolution] [aria-label]')?.getAttribute('aria-label')",
            'Valeur du portefeuille comparée au montant investi.',
        )
        ->assertScript("document.querySelectorAll('[data-perf-row]').length", 5)
        ->assertScript("document.querySelector('[data-perf-bar]').style.width !== ''", true)
        ->assertScript("document.querySelector('[data-perf-row]').title !== ''", true)
        ->assertNoJavaScriptErrors();
});

it('charge la fiche instrument et ses sections, sans erreur', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('ACME')
        ->assertSee('Transactions')
        ->assertSee('Répartition sectorielle')
        ->assertScript("document.querySelector('[data-hero-value]').textContent.trim() !== ''", true)
        ->assertScript("document.querySelectorAll('[data-hero-gain]').length", 1)
        ->assertScript("document.querySelectorAll('[data-hero-gain-pct]').length", 1)
        ->assertScript(
            "document.querySelector('[data-section=valuation] [aria-label]')?.getAttribute('aria-label')",
            'Valeur de la position comparée au montant investi.',
        )
        ->assertNoJavaScriptErrors();
});

it('charge le catalogue et sa liste, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertSee('ACME')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length >= 1", true)
        ->assertNoJavaScriptErrors();
});
