<?php

$markedDot = "(() => {
    const dots = [...document.querySelectorAll('[data-carousel-dot]')];
    const marked = dots.filter((dot) => dot.getAttribute('aria-current') === 'true');

    return marked.length === 1 ? dots.indexOf(marked[0]) : -1;
})()";

$scrolledPage = "(() => {
    const track = document.querySelector('[data-carousel]');

    return Math.round(track.scrollLeft / track.clientWidth);
})()";

it('découpe la fiche instrument en quatre pages glissables sur mobile', function () use ($markedDot) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Transactions')
        ->assertScript("document.querySelectorAll('[data-carousel-page]').length", 4)
        ->assertScript("document.querySelectorAll('[data-carousel-dot]').length", 4)
        ->assertScript($markedDot, 0)
        ->assertNoJavaScriptErrors();
});

it('tient dans la hauteur de l\'écran sur une fiche instrument', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Transactions')
        ->assertScript('document.documentElement.scrollHeight <= window.innerHeight', true)
        ->assertNoJavaScriptErrors();
});

it('mène à la page des secteurs quand on touche son point de pagination', function () use ($markedDot, $scrolledPage) {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Transactions')
        ->assertScript($scrolledPage, 0)
        ->click('[aria-label="Aller à Secteurs"]')
        ->assertScript($scrolledPage, 2)
        ->assertScript($markedDot, 2)
        ->assertNoJavaScriptErrors();
});

it('montre les transactions d\'emblée sur mobile, où la page tient déjà lieu de repli', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Transactions')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->assertScript("document.querySelectorAll('[data-transactions-toggle]').length", 0)
        ->assertNoJavaScriptErrors();
});

it('garde le repli des transactions sur grand écran', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Transactions')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->assertNoJavaScriptErrors();
});

it('dissout le carrousel de la fiche instrument sur grand écran', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Transactions')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel]')).display", 'contents')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel-page]')).display", 'contents')
        ->assertScript("getComputedStyle(document.querySelector('[data-carousel-dots]')).display", 'none')
        ->assertNoJavaScriptErrors();
});

it('garde son fil d\'Ariane dans la boîte d\'un écran', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")->on()->iPhone14Pro()
        ->assertSee('Transactions')
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertScript("(() => {
            const nav = document.querySelector(`nav[aria-label=\"Fil d'Ariane\"]`);

            return Math.round(window.innerHeight - nav.getBoundingClientRect().bottom) <= 1;
        })()", true)
        ->assertNoJavaScriptErrors();
});
