<?php

use App\Contexts\Market\Enums\Sector;

/**
 * Indice du point marqué comme page courante. Rendu par une recherche plutôt que par un sélecteur
 * d'attribut : ça vérifie du même coup qu'un seul point porte la marque.
 */
$markedDot = "(() => {
    const dots = [...document.querySelectorAll('[data-carousel-dot]')];
    const marked = dots.filter((dot) => dot.getAttribute('aria-current') === 'true');

    return marked.length === 1 ? dots.indexOf(marked[0]) : -1;
})()";

$scrolledPage = "(() => {
    const track = document.querySelector('[data-carousel]');

    return Math.round(track.scrollLeft / track.clientWidth);
})()";

it('découpe le tableau de bord en trois pages glissables sur mobile', function () use ($markedDot) {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    // `assertSee` d'abord : les scripts s'exécutent sans attendre, et sur la page encore vide
    // avant l'hydratation de Vue toutes ces mesures valent zéro.
    visit('/')->on()->iPhone14Pro()
        ->assertSee('Performances')
        ->assertScript("document.querySelectorAll('[data-carousel-page]').length", 3)
        ->assertScript("document.querySelectorAll('[data-carousel-dot]').length", 3)
        ->assertScript($markedDot, 0)
        ->assertNoJavaScriptErrors();
});

it('tient dans la hauteur de l\'écran, sans défilement vertical de la page', function () {
    // Un portefeuille chargé : en pile verticale ce contenu dépasse largement l'écran d'un
    // téléphone. C'est ce qui rend l'assertion de hauteur capable d'échouer.
    ['user' => $user] = portfolioFixture();

    foreach (range(1, 6) as $rank) {
        holdingWithSectors($user, "ACME {$rank}", 1000.0 * $rank, [
            Sector::Technology->value => 0.3,
            Sector::Healthcare->value => 0.2,
            Sector::FinancialServices->value => 0.15,
            Sector::CommunicationServices->value => 0.15,
            Sector::ConsumerCyclical->value => 0.1,
            Sector::Industrials->value => 0.1,
        ]);
    }

    $this->actingAs($user);

    visit('/')->on()->iPhone14Pro()
        ->assertSee('Performances')
        ->assertScript('document.documentElement.scrollHeight <= window.innerHeight', true)
        ->assertNoJavaScriptErrors();
});

it('mène à la page correspondante quand on touche un point de pagination', function () use ($markedDot, $scrolledPage) {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')->on()->iPhone14Pro()
        ->assertSee('Performances')
        ->assertScript($scrolledPage, 0)
        ->click('[aria-label="Aller à Performances"]')
        ->assertScript($scrolledPage, 1)
        ->assertScript($markedDot, 1)
        ->assertNoJavaScriptErrors();
});

it('dissout le carrousel sur grand écran pour retrouver la lecture continue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Performances')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel]')).display", 'contents')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel-page]')).display", 'contents')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel-dots]')).display", 'none')
        ->assertNoJavaScriptErrors();
});
