<?php

/** Vrai quand la racine du document porte la classe que Tailwind lit pour son thème sombre. */
function rootIsDark(): string
{
    return '(() => document.documentElement.classList.contains("dark"))()';
}

/** Mode retenu dans le stockage local, celui que le script du layout relira au prochain rendu. */
function storedThemeMode(): string
{
    return '(() => localStorage.getItem("argent-theme"))()';
}

it('affiche le bouton de thème sur le tableau de bord, qui n\'a pas de fil d\'Ariane pour l\'accueillir', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertVisible('[data-theme-toggle]')
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'auto')
        ->assertNoJavaScriptErrors();
});

it('fait passer la page en sombre puis en clair au fil des clics', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    $page->assertScript(rootIsDark(), false);

    $page->click('[data-theme-toggle]')
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'light')
        ->assertScript(rootIsDark(), false);

    $page->click('[data-theme-toggle]')
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'dark')
        ->assertScript(rootIsDark(), true);

    $page->click('[data-theme-toggle]')
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'auto');

    $page->assertNoJavaScriptErrors();
});

it('retrouve le thème sombre après un rechargement, sans repasser par le clair', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    $page->click('[data-theme-toggle]')
        ->click('[data-theme-toggle]')
        ->assertScript(storedThemeMode(), 'dark');

    $page->navigate("/instruments/{$instrument->id}")
        ->assertScript(rootIsDark(), true)
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'dark')
        ->assertNoJavaScriptErrors();
});
