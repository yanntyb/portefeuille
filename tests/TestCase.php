<?php

namespace Tests;

use App\Domains\User\Models\User;
use App\Infrastructure\Services\UserId;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function authenticateRandomUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function actingAs($user, $guard = null)
    {
        // Set UserId override when authenticating via $this->actingAs()
        app(UserId::class)->setOverride($user->id ?? $user->getKey());

        return parent::actingAs($user, $guard);
    }
}
