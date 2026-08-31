<?php

namespace App\Contexts\Portfolio\Observers;

use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Actions\RecomputeRealizedGains;
use App\Contexts\Portfolio\Models\Transaction;

class TransactionObserver
{
    public function __construct(
        private CalculateRealizedGain $calculateRealizedGain,
        private ProjectHolding $projectHolding,
        private RecomputeRealizedGains $recomputeRealizedGains,
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
                $originalUserId = (int) $transaction->getOriginal('user_id');
                $originalWalletId = (int) $transaction->getOriginal('wallet_id');

                ($this->projectHolding)($originalUserId, (int) $originalAssetId, $originalWalletId);
                ($this->recomputeRealizedGains)($originalUserId, (int) $originalAssetId, $originalWalletId);
            }
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    /**
     * La position ET les gains réalisés de l'enveloppe touchée. Les deux pour la même raison : un
     * achat modifié ou supprimé change le prix de revient, donc la position, donc le gain de
     * chaque vente postérieure — que `CalculateRealizedGain` ne recalcule que pour la ligne qui
     * bouge.
     */
    private function project(Transaction $transaction): void
    {
        if ($transaction->asset_id === null) {
            return;
        }

        ($this->projectHolding)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
        ($this->recomputeRealizedGains)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
    }
}
