<?php

use App\Contexts\Identity\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('rend un patrimoine vide sans aucune donnée', function () {
    User::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn (float $value): bool => $value === 0.0)
            ->where('overview.totalGainPct', null)
        );
});

it('additionne les titres et l\'immobilier dans le grand chiffre', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('overview.securities')
            ->has('overview.realEstate')
            ->where('overview.totalValue', fn (float $total): bool => $total > 0.0)
        );
});

it('diffère la série du patrimoine', function () {
    Carbon::setTestNow('2026-08-21');
    propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('series')
        );
});
