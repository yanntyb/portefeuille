<?php

namespace App\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;

abstract class DomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable,
    ) {}
}
