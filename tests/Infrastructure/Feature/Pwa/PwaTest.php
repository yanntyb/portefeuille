<?php

use App\Domains\User\Models\User;

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

test('authenticated user can access admin panel', function () {
    $this->actingAs(User::factory()->create());

    // Admin panel is at the root path (/)
    // First get the redirect, then follow to the actual page
    $response = $this->get('/');

    if ($response->status() === 302) {
        $redirectTo = $response->headers->get('Location');
        $response = $this->get($redirectTo);
    }

    $response->assertSuccessful();
    expect($response->status())->toBe(200);
});
