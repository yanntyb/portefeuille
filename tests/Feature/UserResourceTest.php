<?php

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

it('allows an admin to access the user list page', function () {
    actingAs(User::factory()->admin()->create());

    $users = User::factory()->count(3)->create();

    livewire(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users);
});

it('denies a standard user access to the user list page', function () {
    livewire(ListUsers::class)
        ->assertForbidden();
});

it('allows an admin to create a user', function () {
    actingAs(User::factory()->admin()->create());

    $newUser = User::factory()->make();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => $newUser->name,
            'email' => $newUser->email,
            'password' => 'password',
            'role' => Role::User->value,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => $newUser->name,
        'email' => $newUser->email,
        'role' => 'user',
    ]);
});

it('allows an admin to update a user', function () {
    actingAs(User::factory()->admin()->create());

    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Nouveau Nom',
            'email' => $user->email,
            'role' => Role::Admin->value,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(User::class, [
        'id' => $user->id,
        'name' => 'Nouveau Nom',
        'role' => 'admin',
    ]);
});

it('allows an admin to delete a user', function () {
    actingAs(User::factory()->admin()->create());

    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseMissing($user);
});

it('shows impersonate action for admin users on non-admin targets', function () {
    actingAs(User::factory()->admin()->create());

    $target = User::factory()->create();

    livewire(ListUsers::class)
        ->assertTableActionVisible('impersonate', $target);
});

it('hides impersonate action for admin targets', function () {
    actingAs(User::factory()->admin()->create());

    $adminTarget = User::factory()->admin()->create();

    livewire(ListUsers::class)
        ->assertTableActionHidden('impersonate', $adminTarget);
});
