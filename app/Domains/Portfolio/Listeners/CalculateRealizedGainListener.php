<?php

namespace App\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Events\TransactionCreated;

class CalculateRealizedGainListener
{
    public function handle(TransactionCreated $event): void
    {
        // Observer already calculated realized gain before dispatch.
        // This listener exists for future event-driven logic,
        // such as updating read models or triggering downstream commands.
    }
}
