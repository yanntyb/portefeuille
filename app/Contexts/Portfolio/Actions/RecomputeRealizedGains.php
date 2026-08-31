<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

/**
 * Le gain réalisé de toutes les ventes d'une enveloppe, recalculé.
 *
 * `CalculateRealizedGain` dérive le prix de revient des achats **antérieurs** à la vente, et la
 * colonne `realized_gain` est stockée puis relue telle quelle par `GetRealizedGains`. Un achat
 * corrigé ou supprimé rend donc faux le gain de toutes les ventes qui le suivent — invisible tant
 * que seuls les seeders écrivaient, dans l'ordre, mais garanti dès la première correction de
 * saisie.
 *
 * L'observateur ne peut pas s'en charger ligne à ligne : il ne voit que celle qui bouge.
 */
class RecomputeRealizedGains
{
    public function __construct(private CalculateRealizedGain $calculate) {}

    public function __invoke(int $userId, int $assetId, int $walletId): void
    {
        $sells = Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->where('wallet_id', $walletId)
            ->where('type', TransactionType::Sell)
            ->get();

        foreach ($sells as $sell) {
            $sell->realized_gain = ($this->calculate)($sell);

            /** Silencieux, sans quoi `updating` rappellerait cette action sans fin. */
            $sell->saveQuietly();
        }
    }
}
