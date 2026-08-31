<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\CostBasis;

/**
 * Ce qu'une enveloppe détient d'un actif, et à quel prix : les achats moins les ventes, prix de
 * revient compris. Site unique de cette arithmétique — `ProjectHolding` la portait en ligne, et le
 * contrôle de survente à la saisie en a besoin lui aussi.
 *
 * Le couple `(asset_id, wallet_id)` est la clé de `holdings_projection` : un même actif tenu dans
 * deux enveloppes fait deux stocks, jamais un.
 */
class GetPositionStock
{
    public function __construct(private CostBasis $costBasis) {}

    /**
     * `$ignoringTransactionId` sert l'édition d'une vente : la quantité disponible doit se lire
     * comme si la ligne éditée n'existait pas, sans quoi porter une vente de 4 à 5 se comparerait
     * à un stock dont ses propres 4 titres sont déjà déduits.
     *
     * Volontairement aveugle aux dates, comme la projection elle-même : le stock est un solde, pas
     * une chronologie.
     *
     * @return array{quantity: float, avgCost: float}
     */
    public function __invoke(int $userId, int $assetId, int $walletId, ?int $ignoringTransactionId = null): array
    {
        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->where('wallet_id', $walletId)
            ->when($ignoringTransactionId !== null, fn ($query) => $query->whereKeyNot($ignoringTransactionId))
            ->get();

        $buys = $this->costBasis->of(
            $transactions->where('type', TransactionType::Buy)
                ->map(fn (Transaction $transaction): array => [
                    'quantity' => (float) $transaction->quantity,
                    'unitPrice' => (float) $transaction->unit_price,
                    'fees' => (float) $transaction->fees,
                ])
                ->values()
                ->all(),
        );

        $sold = (float) $transactions->where('type', TransactionType::Sell)->sum('quantity');

        return [
            'quantity' => $buys['quantity'] - $sold,
            'avgCost' => $buys['average'],
        ];
    }
}
