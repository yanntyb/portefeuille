<?php

/**
 * Reporte si le navigateur a déjà demandé le chemin donné.
 */
function hasRequestedPath(string $path): string
{
    return "performance.getEntriesByType('resource').some(entry => new URL(entry.name).pathname === '{$path}')";
}

it('précharge la fiche instrument au survol d\'une ligne du tableau de bord', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture(['name' => 'Alpha', 'ticker' => 'ALP']);

    $this->actingAs($user);

    $page = visit('/')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeFalse();

    $page->hover('[data-instrument-name]')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeTrue();
});
