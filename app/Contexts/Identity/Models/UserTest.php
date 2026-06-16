<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;

it('cast le role en enum', function () {
    $user = User::factory()->create(['role' => Role::User]);

    expect($user->refresh()->role)->toBe(Role::User);
});

it('hash le mot de passe', function () {
    $user = User::factory()->create(['password' => 'secret-pass']);

    expect(Hash::check('secret-pass', $user->password))->toBeTrue();
});

it('cache le mot de passe et le remember_token dans le tableau', function () {
    $user = User::factory()->create();

    expect($user->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('crée un admin via le state admin()', function () {
    expect(User::factory()->admin()->create()->role)->toBe(Role::Admin);
});

it('crée un utilisateur non vérifié via unverified()', function () {
    expect(User::factory()->unverified()->create()->email_verified_at)->toBeNull();
});

it('autorise l’accès au panel Filament', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Mockery::mock(Panel::class)))->toBeTrue();
});
