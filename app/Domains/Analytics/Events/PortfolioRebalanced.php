<?php

namespace App\Domains\Analytics\Events;

use App\Domains\User\Models\User;
use App\Shared\Events\DomainEvent;

class PortfolioRebalanced extends DomainEvent
{
    public function __construct(
        public readonly User $user,
        public readonly array $changes,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct($occurredAt ?? new \DateTimeImmutable);
    }
}
