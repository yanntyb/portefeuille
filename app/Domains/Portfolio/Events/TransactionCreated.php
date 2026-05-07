<?php

namespace App\Domains\Portfolio\Events;

use App\Domains\Portfolio\Models\Transaction;
use App\Shared\Events\DomainEvent;

class TransactionCreated extends DomainEvent
{
    public function __construct(
        public readonly Transaction $transaction,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct($occurredAt ?? new \DateTimeImmutable);
    }
}
