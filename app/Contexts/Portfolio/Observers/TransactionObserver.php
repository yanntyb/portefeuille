<?php

namespace App\Contexts\Portfolio\Observers;

use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Models\Transaction;

class TransactionObserver
{
    public function __construct(
        private CalculateRealizedGain $calculateRealizedGain,
        private ProjectHolding $projectHolding,
    ) {}

    public function creating(Transaction $transaction): void
    {
        $transaction->realized_gain = ($this->calculateRealizedGain)($transaction);
    }

    public function updating(Transaction $transaction): void
    {
        $transaction->realized_gain = ($this->calculateRealizedGain)($transaction);
    }

    public function created(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->project($transaction);

        if ($transaction->wasChanged('asset_id') || $transaction->wasChanged('wallet_id')) {
            $originalAssetId = $transaction->getOriginal('asset_id');
            if ($originalAssetId !== null) {
                ($this->projectHolding)(
                    (int) $transaction->getOriginal('user_id'),
                    (int) $originalAssetId,
                    (int) $transaction->getOriginal('wallet_id'),
                );
            }
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    private function project(Transaction $transaction): void
    {
        if ($transaction->asset_id === null) {
            return;
        }

        ($this->projectHolding)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
    }
}
