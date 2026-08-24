<?php

use App\Contexts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Contrairement à `app/Contexts`, `app/Shared` n'active pas `RefreshDatabase` par défaut (voir
 * `tests/Pest.php`) : ce test est le premier du dossier à toucher la base, donc il l'active
 * lui-même plutôt que d'élargir la configuration globale pour un seul fichier.
 */
uses(RefreshDatabase::class);

it('sert l\'instantané complet du patrimoine', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)
        ->getJson(route('pwa.snapshot'))
        ->assertOk()
        ->assertJsonStructure([
            'version',
            'generatedAt',
            'dashboard' => ['overview', 'series', 'income'],
            'classes' => ['equity', 'bond', 'commodity', 'crypto'],
            'assets',
            'properties' => ['list', 'byId'],
        ]);
});

it('garde la même version tant que les données ne bougent pas', function () {
    ['user' => $user] = portfolioFixture();

    $first = $this->actingAs($user)->getJson(route('pwa.snapshot'))->json('version');
    $second = $this->actingAs($user)->getJson(route('pwa.snapshot'))->json('version');

    expect($second)->toBe($first);
});

it('répond sans données quand aucun utilisateur n\'existe', function () {
    User::query()->delete();

    $this->getJson(route('pwa.snapshot'))
        ->assertOk()
        ->assertJsonPath('dashboard.overview.totalValue', 0);
});
