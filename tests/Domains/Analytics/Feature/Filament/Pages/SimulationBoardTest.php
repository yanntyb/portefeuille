<?php

use App\Domains\Analytics\Filament\Pages\SimulationBoard;
use App\Domains\User\Enums\Role;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('admin can access simulation board', function () {
    $user = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($user);

    livewire(SimulationBoard::class)
        ->assertSuccessful();
});

it('non-admin user gets forbidden error', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $this->actingAs($user);

    livewire(SimulationBoard::class)
        ->assertForbidden();
});

it('unauthenticated user is redirected to login', function () {
    $this->get('/admin/analytics/simulation-board')
        ->assertRedirect();
});

it('navigation items returned for all users', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $this->actingAs($user);

    $items = SimulationBoard::getNavigationItems();

    expect($items)->toHaveCount(1);
});
