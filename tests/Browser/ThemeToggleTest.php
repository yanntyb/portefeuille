<?php

/** Vrai quand la racine du document porte la classe que Tailwind lit pour son thème sombre. */
function rootIsDark(): string
{
    return '(() => document.documentElement.classList.contains("dark"))()';
}

/** Distance en pixels entre le bas de la barre d'outils et le bas de la fenêtre. */
function bottomBarGapToViewportBottom(): string
{
    return '(() => {
        const bar = document.querySelector("[data-bottom-bar]");

        return Math.round(window.innerHeight - bar.getBoundingClientRect().bottom);
    })()';
}

/** Distance en pixels entre le haut de la fenêtre et la première valeur du tableau de bord. */
function wealthValueTopOffset(): string
{
    return '(() => Math.round(document.querySelector("[data-wealth-value]").getBoundingClientRect().top))()';
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

it('loge le bouton dans la barre collante du bas, collée au bord de la fenêtre', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    $page = visit("/asset/{$instrument->id}");

    $page->assertScript('(() => document.querySelector("[data-bottom-bar]").contains(document.querySelector("[data-theme-toggle]")))()', true);

    expect($page->script(bottomBarGapToViewportBottom()))->toBeLessThanOrEqual(1);

    $page->assertNoJavaScriptErrors();
});

it('laisse le contenu du tableau de bord en haut de page : le bouton ne pousse rien vers le bas', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    expect($page->script(wealthValueTopOffset()))->toBeLessThanOrEqual(48);

    $page->assertNoJavaScriptErrors();
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

    $page->navigate("/asset/{$instrument->id}")
        ->assertScript(rootIsDark(), true)
        ->assertAttribute('[data-theme-toggle]', 'data-theme-mode', 'dark')
        ->assertNoJavaScriptErrors();
});
