<?php

namespace App\Contexts\Income\Sources\Dividend\Services;

use App\Contexts\Income\Sources\Dividend\Datas\ConfirmedDividendData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;

/**
 * Site unique de la substitution d'un détachement calculé par sa transaction confirmée : sans
 * lui, chaque lecteur (revenu global, fiche d'un actif) referait sa propre règle, et deux montants
 * différents finiraient par circuler pour le même fait.
 *
 * La clé de correspondance est `(assetId, walletId, exDate)`, la même que celle du
 * dédoublonnage dans `DividendIncomeSource`.
 */
class ConfirmedDividendSubstitution
{
    /**
     * Écarte tout reçu calculé dont il existe une transaction confirmée, et la substitue à sa
     * place — jamais les deux. La quantité et le montant par action d'un reçu substitué sont
     * repris du calcul quand il existait, faute de quoi une transaction ne les porte pas ;
     * `amount`, lui, vient toujours de la transaction, jamais recalculé.
     *
     * @param  list<DividendReceiptData>  $receipts
     * @param  list<ConfirmedDividendData>  $confirmed
     * @return list<DividendReceiptData>
     */
    public function apply(array $receipts, array $confirmed): array
    {
        if ($confirmed === []) {
            return $receipts;
        }

        /** @var array<string, DividendReceiptData> $derivedByKey */
        $derivedByKey = [];
        foreach ($receipts as $receipt) {
            $derivedByKey[$this->key($receipt->assetId, $receipt->walletId, $receipt->exDate)] = $receipt;
        }

        /** @var array<string, true> $confirmedKeys */
        $confirmedKeys = [];
        foreach ($confirmed as $dividend) {
            $confirmedKeys[$this->key($dividend->assetId, $dividend->walletId, $dividend->exDate)] = true;
        }

        $merged = array_values(array_filter(
            $receipts,
            fn (DividendReceiptData $receipt): bool => ! isset($confirmedKeys[$this->key($receipt->assetId, $receipt->walletId, $receipt->exDate)]),
        ));

        foreach ($confirmed as $dividend) {
            $derived = $derivedByKey[$this->key($dividend->assetId, $dividend->walletId, $dividend->exDate)] ?? null;
            $quantity = $derived?->quantity ?? 0.0;

            $merged[] = new DividendReceiptData(
                assetId: $dividend->assetId,
                walletId: $dividend->walletId,
                exDate: $dividend->exDate,
                quantity: $quantity,
                amountPerShare: $quantity > 0.0 ? round($dividend->amount / $quantity, 6) : 0.0,
                amount: $dividend->amount,
            );
        }

        usort($merged, fn (DividendReceiptData $a, DividendReceiptData $b): int => $b->exDate <=> $a->exDate);

        return $merged;
    }

    private function key(int $assetId, int $walletId, string $exDate): string
    {
        return "{$assetId}|{$walletId}|{$exDate}";
    }
}
