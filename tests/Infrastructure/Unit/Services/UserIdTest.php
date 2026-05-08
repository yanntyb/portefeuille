<?php

use App\Domains\User\Models\User;
use App\Infrastructure\Services\UserId;

it('returns authenticated user id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $service = app(UserId::class);

    expect($service->get())->toBe($user->id);
});

it('throws exception when user not authenticated', function () {
    $service = app(UserId::class);

    $this->expectException(Exception::class);
    $service->get();
});

it('is resolved from container', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(app(UserId::class))->toBeInstanceOf(UserId::class)
        ->and(app(UserId::class)->get())->toBe($user->id);
});
