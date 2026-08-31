<?php

use App\Contexts\Identity\Models\User;

/**
 * Aucun instrument dans ces tests, et c'est délibéré : la file tourne en `sync` sous PHPUnit, donc
 * un clic exécute vraiment la synchronisation. Sans instrument à coter, aucune des trois sources
 * n'appelle le fournisseur — le test ne sort jamais sur le réseau.
 */
it('met la synchronisation en file depuis la barre du bas', function () {
    $this->actingAs(User::factory()->create());

    visit('/')
        ->assertVisible('[data-bottom-bar] [data-sync-button][data-sync-status="idle"]')
        ->click('[data-sync-button]')
        /** Le sondage ramène l'état d'arrivée sans que la page soit rechargée. */
        ->assertVisible('[data-sync-button][data-sync-status="succeeded"]')
        ->assertNoJavaScriptErrors();
});

it('ne montre le bouton que sur le tableau de bord', function () {
    $this->actingAs(User::factory()->create());

    visit('/actions')
        ->assertVisible('[data-bottom-bar] [data-theme-toggle]')
        ->assertMissing('[data-sync-button]')
        ->assertNoJavaScriptErrors();
});
