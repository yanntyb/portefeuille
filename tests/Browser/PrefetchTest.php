<?php

/**
 * Reporte si le navigateur a déjà demandé le chemin donné.
 */
function hasRequestedPath(string $path): string
{
    return "performance.getEntriesByType('resource').some(entry => new URL(entry.name).pathname === '{$path}')";
}

it('précharge la fiche instrument au survol d\'une ligne du catalogue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture(['name' => 'Alpha', 'ticker' => 'ALP']);

    $this->actingAs($user);

    $page = visit('/instruments')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeFalse();

    $page->hover('[data-catalog-row] a')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeTrue();
});

it('précharge la fiche instrument au survol d\'une position du tableau de bord', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture(['name' => 'Alpha', 'ticker' => 'ALP']);

    $this->actingAs($user);

    $page = visit('/')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeFalse();

    $page->hover('[data-holding-name]')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeTrue();
});

it('précharge le catalogue au survol du fil d\'Ariane depuis une fiche instrument', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture(['name' => 'Alpha', 'ticker' => 'ALP']);

    $this->actingAs($user);

    $page = visit("/instruments/{$instrument->id}")->assertSee('Alpha');

    expect($page->script(hasRequestedPath('/instruments')))->toBeFalse();

    $page->hover('nav[aria-label="Fil d\'Ariane"] a[href="/instruments"]')->wait(1);

    expect($page->script(hasRequestedPath('/instruments')))->toBeTrue();
});
