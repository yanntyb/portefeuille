<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Inertia\Testing\AssertableInertia;

it('renders the property page with its deferred amortization', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    Lease::factory()->ongoing()->create(['property_id' => $property->id, 'monthly_rent' => 600]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'value' => 120000]);

    $this->get(route('properties.show', $property->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Properties/Detail')
            ->where('property.name', 'T2 Lyon 7e')
            ->has('property.metrics'));
});

it('renders 404 for an unknown property', function () {
    User::factory()->create();

    $this->get(route('properties.show', 999))->assertNotFound();
});

it('defers the real estate overview on the dashboard', function () {
    User::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});
