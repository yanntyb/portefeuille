<?php

it('affiche un fil d\'Ariane collant sur le tableau de bord', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Tableau de bord')
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertAttribute('nav[aria-label="Fil d\'Ariane"] [aria-current="page"]', 'aria-current', 'page')
        ->assertNoJavaScriptErrors();
});

it('affiche le fil d\'Ariane complet sur une fiche instrument', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Tableau de bord')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Instruments')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'ACME')
        ->assertNoJavaScriptErrors();
});
