<?php

use App\Domains\User\Models\User;
use Database\Seeders\DemoSeeder;

it('demo seeder creates demo user', function () {
    User::query()->delete();

    $seeder = app(DemoSeeder::class);
    $user = $seeder->run();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('demo@example.com')
        ->and($user->name)->toBe('Démo');
});

it('demo seeder clears existing user data', function () {
    $seeder = app(DemoSeeder::class);
    $user = $seeder->run();

    User::query()->delete();
    $seeder->run();

    expect(User::count())->toBe(1);
});

it('demo user created by seeder can be authenticated', function () {
    User::query()->delete();

    $seeder = app(DemoSeeder::class);
    $user = $seeder->run();

    $this->actingAs($user);

    $this->assertAuthenticated();
});
