<?php

namespace Tests\Feature\Domains\Security\Events;

use App\Domains\Security\Events\PriceUpdated;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use Tests\TestCase;

class PriceUpdatedTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_creates_event_with_price_and_security(): void
    {
        $security = Security::factory()->create();
        $price = SecurityPrice::factory()->for($security)->create();

        $event = new PriceUpdated($price, $security);

        $this->assertSame($price->id, $event->price->id);
        $this->assertSame($security->id, $event->security->id);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt);
    }

    public function test_stores_price_reference(): void
    {
        $security = Security::factory()->create();
        $price = SecurityPrice::factory()->for($security)->create();

        $event = new PriceUpdated($price, $security);

        $this->assertInstanceOf(SecurityPrice::class, $event->price);
    }

    public function test_stores_security_reference(): void
    {
        $security = Security::factory()->create();
        $price = SecurityPrice::factory()->for($security)->create();

        $event = new PriceUpdated($price, $security);

        $this->assertInstanceOf(Security::class, $event->security);
    }

    public function test_uses_custom_timestamp(): void
    {
        $security = Security::factory()->create();
        $price = SecurityPrice::factory()->for($security)->create();
        $customTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new PriceUpdated($price, $security, $customTime);

        $this->assertEquals($customTime, $event->occurredAt);
    }
}
