<?php

test('manifest.json returns valid JSON with required keys', function () {
    $response = $this->get('/manifest.json');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'name',
            'short_name',
            'description',
            'start_url',
            'scope',
            'display',
            'theme_color',
            'background_color',
            'icons',
        ])
        ->assertJson([
            'name' => config('pwa.name'),
            'short_name' => config('pwa.short_name'),
            'display' => 'standalone',
        ]);
});

test('service worker returns JavaScript content', function () {
    $response = $this->get('/sw.js');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/javascript');

    expect($response->getContent())->toContain('CACHE_NAME')
        ->toContain('addEventListener');
});

test('admin panel contains PWA meta tags', function () {
    $response = $this->get('/admin');

    $response->assertSuccessful();

    $content = $response->getContent();

    expect($content)
        ->toContain('rel="manifest"')
        ->toContain('name="theme-color"')
        ->toContain('name="apple-mobile-web-app-capable"');
});
