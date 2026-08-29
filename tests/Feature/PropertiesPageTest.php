<?php

use App\Contexts\Identity\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('rend la page Immobilier vide sans aucun bien', function () {
    User::factory()->create();

    $this->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('realEstate.properties', 0)
        );
});
