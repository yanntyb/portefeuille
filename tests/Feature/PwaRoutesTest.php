<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;

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

it('sert un worker inerte tant que le runtime n\'est pas compilé', function () {
    $runtime = hideServiceWorkerRuntime();

    try {
        $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript')
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertSee('unregister');
    } finally {
        restoreServiceWorkerRuntime($runtime);
    }
});

it('injecte la version de cache et les URLs à précacher dans le worker', function () {
    $hot = hideViteHotFile();
    $runtime = hideServiceWorkerRuntime();
    File::put(public_path('sw-runtime.js'), '/* runtime compilé */');

    try {
        $body = $this->get('/sw.js')->assertOk()->getContent();

        expect(Vite::manifestHash())->not->toBeNull();
        expect($body)
            ->toContain('self.CACHE_VERSION = '.json_encode(Vite::manifestHash()))
            ->toContain('/hors-ligne')
            ->toContain('/build/')
            ->toContain('/* runtime compilé */');
    } finally {
        restoreServiceWorkerRuntime($runtime);
        restoreViteHotFile($hot);
    }
});
