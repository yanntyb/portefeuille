<?php

namespace App\Domains\Portfolio\Events;

use App\Domains\Portfolio\Models\Transaction;
use App\Shared\Events\DomainEvent;

class TransactionCancelled extends DomainEvent
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly ?string $reason = null,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        parent::__construct($occurredAt ?? new \DateTimeImmutable);
    }
}
