<?php

namespace App\Domains\Security\Events;

use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Shared\Events\DomainEvent;

class PriceUpdated extends DomainEvent
{
    public function __construct(
        public readonly SecurityPrice $price,
        public readonly Security $security,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct($occurredAt ?? new \DateTimeImmutable);
    }
}
