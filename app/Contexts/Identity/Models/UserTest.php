<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
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

it('exposes its wallets', function () {
    $user = User::factory()->create();
    Wallet::factory()->for($user)->create(['name' => 'PEA']);
    Wallet::factory()->for($user)->create(['name' => 'CTO']);
    Wallet::factory()->for(User::factory()->create())->create(['name' => 'PEA']);

    expect($user->wallets)->toHaveCount(2)
        ->and($user->wallets->pluck('name')->sort()->values()->all())->toBe(['CTO', 'PEA']);
});

it('exposes its transactions', function () {
    $user = User::factory()->create();
    Transaction::factory()->count(3)->for($user)->create();
    Transaction::factory()->for(User::factory()->create())->create();

    expect($user->transactions)->toHaveCount(3);
});

it('exposes its holdings', function () {
    $user = User::factory()->create();
    Holding::factory()->count(2)->for($user)->create();
    Holding::factory()->for(User::factory()->create())->create();

    expect($user->holdings)->toHaveCount(2);
});
