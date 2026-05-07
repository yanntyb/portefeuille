<?php

namespace App\Domains\Portfolio\Observers;

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Services\RealizedGainCalculator;

class TransactionObserver
{
    public function __construct(private RealizedGainCalculator $calculator) {}

    public function creating(Transaction $transaction): void
    {
        $this->syncRealizedGain($transaction);
    }

    public function updating(Transaction $transaction): void
    {
        $this->syncRealizedGain($transaction);
    }

    public function created(Transaction $transaction): void
    {
        TransactionCreated::dispatch($transaction);
    }

    private function syncRealizedGain(Transaction $transaction): void
    {
        $transaction->realized_gain = $this->calculator->calculate($transaction);
    }
}
