<?php

namespace Tests\Feature\Domains\Analytics\Events;

use App\Domains\Analytics\Events\PortfolioRebalanced;
use App\Domains\User\Models\User;
use Tests\TestCase;

class PortfolioRebalancedTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_creates_event_with_user_and_changes(): void
    {
        $user = User::factory()->create();
        $changes = [
            ['name' => 'AAPL', 'shares_to_buy' => 10, 'buy_cost' => 1500],
        ];

        $event = new PortfolioRebalanced($user, $changes);

        $this->assertSame($user->id, $event->user->id);
        $this->assertSame($changes, $event->changes);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt);
    }

    public function test_stores_user_reference(): void
    {
        $user = User::factory()->create();
        $changes = [];

        $event = new PortfolioRebalanced($user, $changes);

        $this->assertInstanceOf(User::class, $event->user);
    }

    public function test_stores_changes_array(): void
    {
        $user = User::factory()->create();
        $changes = [
            ['name' => 'AAPL', 'shares_to_buy' => 10],
            ['name' => 'GOOGL', 'shares_to_buy' => 5],
        ];

        $event = new PortfolioRebalanced($user, $changes);

        $this->assertCount(2, $event->changes);
        $this->assertSame($changes, $event->changes);
    }

    public function test_uses_custom_timestamp(): void
    {
        $user = User::factory()->create();
        $changes = [];
        $customTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new PortfolioRebalanced($user, $changes, $customTime);

        $this->assertEquals($customTime, $event->occurredAt);
    }
}
