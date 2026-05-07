<?php

namespace App\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Services\RealizedGainCalculator;

class CalculateRealizedGainListener
{
    public function handle(TransactionCreated $event): void
    {
        $transaction = $event->transaction;
        $gain = (new RealizedGainCalculator)->calculate($transaction);

        if ($gain !== null || $transaction->realized_gain !== null) {
            $transaction->update(['realized_gain' => $gain]);
        }
    }
}
