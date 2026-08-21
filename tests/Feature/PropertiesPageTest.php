<?php

use App\Contexts\Identity\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('rend la page Immobilier avec les biens de l\'utilisateur', function () {
    Carbon::setTestNow('2026-08-21');
    propertyFixture(['loan' => true]);

    $this->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('realEstate.properties', 1)
            ->where('realEstate.properties.0.name', 'T2 Lyon 7e')
        );
});

it('rend la page Immobilier vide sans aucun bien', function () {
    User::factory()->create();

    $this->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('realEstate.properties', 0)
        );
});
