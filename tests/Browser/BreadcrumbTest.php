<?php

/** Distance en pixels entre le bas du fil d'Ariane et le bas de la fenêtre. */
function breadcrumbGapToViewportBottom(): string
{
    return '(() => {
        const nav = document.querySelector(`nav[aria-label="Fil d\'Ariane"]`);

        return Math.round(window.innerHeight - nav.getBoundingClientRect().bottom);
    })()';
}

it('affiche un fil d\'Ariane collant en bas du tableau de bord', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Tableau de bord')
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertAttribute('nav[aria-label="Fil d\'Ariane"] [aria-current="page"]', 'aria-current', 'page');

    expect($page->script(breadcrumbGapToViewportBottom()))->toBeLessThanOrEqual(1);

    $page->assertNoJavaScriptErrors();
});

it('place le fil d\'Ariane après le contenu de la page', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    $page = visit('/');

    $page->assertScript('(() => {
        const main = document.querySelector("main");
        const nav = document.querySelector(`nav[aria-label="Fil d\'Ariane"]`);

        return Boolean(main.compareDocumentPosition(nav) & Node.DOCUMENT_POSITION_FOLLOWING);
    })()', true);

    $page->assertNoJavaScriptErrors();
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
