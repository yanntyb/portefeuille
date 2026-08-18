<?php

it('sert un manifest conforme à la configuration', function () {
    $this->get('/manifest.json')
        ->assertOk()
        ->assertJsonPath('short_name', config('pwa.short_name'))
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/')
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.sizes', '512x512');
});

it('sert une page hors-ligne autonome', function () {
    $this->get('/hors-ligne')
        ->assertOk()
        ->assertSee('Pas de connexion')
        ->assertSee('Les données affichées');
});

it('déclare le manifest et le point de montage du bandeau dans le layout', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('id="pwa-banner"', false);
});
