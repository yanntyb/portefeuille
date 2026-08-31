<?php

namespace App\Contexts\Portfolio\Observers;

use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Actions\RecomputeCashDeposits;
use App\Contexts\Portfolio\Actions\RecomputeRealizedGains;
use App\Contexts\Portfolio\Models\Transaction;

class TransactionObserver
{
    public function __construct(
        private CalculateRealizedGain $calculateRealizedGain,
        private ProjectHolding $projectHolding,
        private RecomputeRealizedGains $recomputeRealizedGains,
        private RecomputeCashDeposits $recomputeCashDeposits,
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
            $originalUserId = (int) $transaction->getOriginal('user_id');
            $originalWalletId = (int) $transaction->getOriginal('wallet_id');

            $originalAssetId = $transaction->getOriginal('asset_id');
            if ($originalAssetId !== null) {
                ($this->projectHolding)($originalUserId, (int) $originalAssetId, $originalWalletId);
                ($this->recomputeRealizedGains)($originalUserId, (int) $originalAssetId, $originalWalletId);
            }

            /** L'enveloppe d'origine perd un mouvement d'espèces : ses versements déduits en dépendent aussi. */
            ($this->recomputeCashDeposits)($originalUserId, $originalWalletId);
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    /**
     * Les espèces, la position ET les gains réalisés de l'enveloppe touchée.
     *
     * Les espèces se recalculent sur toute transaction, versement compris : un versement saisi
     * n'a pas d'actif mais reste un mouvement d'espèces. La position et le gain réalisé, eux,
     * restent conditionnés à l'actif — un achat modifié ou supprimé change le prix de revient,
     * donc la position, donc le gain de chaque vente postérieure, que `CalculateRealizedGain` ne
     * recalcule que pour la ligne qui bouge.
     */
    private function project(Transaction $transaction): void
    {
        ($this->recomputeCashDeposits)($transaction->user_id, $transaction->wallet_id);

        if ($transaction->asset_id === null) {
            return;
        }

        ($this->projectHolding)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
        ($this->recomputeRealizedGains)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
    }
}
