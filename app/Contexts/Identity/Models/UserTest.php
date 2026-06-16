<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;

it('casts the role to an enum', function () {
    $user = User::factory()->create(['role' => Role::User]);

    expect($user->refresh()->role)->toBe(Role::User);
});

it('hashes the password', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    expect(Hash::check('secret-pass', $user->password))->toBeTrue();
});

it('hides the password and remember_token from the array', function () {
    $user = User::factory()->create();

    expect($user->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('creates an admin via the admin() state', function () {
    expect(User::factory()->admin()->create()->role)->toBe(Role::Admin);
});

it('creates an unverified user via unverified()', function () {
    expect(User::factory()->unverified()->create()->email_verified_at)->toBeNull();
});

it('allows access to the Filament panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Mockery::mock(Panel::class)))->toBeTrue();
});
