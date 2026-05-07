<?php

namespace Tests\Feature\Domains\Portfolio\Events;

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\Transaction;
use Tests\TestCase;

class TransactionCreatedTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_creates_event_with_transaction(): void
    {
        $transaction = Transaction::factory()->create();

        $event = new TransactionCreated($transaction);

        $this->assertSame($transaction->id, $event->transaction->id);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt);
    }

    public function test_stores_transaction_reference(): void
    {
        $transaction = Transaction::factory()->create();

        $event = new TransactionCreated($transaction);

        $this->assertInstanceOf(Transaction::class, $event->transaction);
    }

    public function test_uses_custom_timestamp(): void
    {
        $transaction = Transaction::factory()->create();
        $customTime = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TransactionCreated($transaction, $customTime);

        $this->assertEquals($customTime, $event->occurredAt);
    }
}
