<?php

namespace App\Contexts\Income\Sources\Dividend\Services;

use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use Illuminate\Support\Carbon;

class DividendCalculator
{
    /**
     * Croise les détachements avec les quantités détenues pour rendre les dividendes perçus,
     * du plus récent au plus ancien.
     *
     * Le regroupement se fait par actif ET par enveloppe : deux comptes détenant le même titre
     * reçoivent chacun leur propre versement, jamais un versement unique agrégé.
     *
     * @param  list<PositionRecordData>  $transactions  tous actifs confondus, ordre indifférent
     * @param  list<DividendRecordData>  $dividends
     * @return list<DividendReceiptData>
     */
    public function receipts(array $transactions, array $dividends): array
    {
        /** @var array<int, array<int, list<PositionRecordData>>> $movements */
        $movements = [];

        foreach ($transactions as $transaction) {
            $movements[$transaction->assetId][$transaction->walletId][] = $transaction;
        }

        $receipts = [];

        foreach ($dividends as $dividend) {
            foreach ($movements[$dividend->assetId] ?? [] as $walletId => $walletMovements) {
                $quantity = $this->quantityBefore($walletMovements, $dividend->exDate);

                if ($quantity <= 0.0) {
                    continue;
                }

                $receipts[] = new DividendReceiptData(
                    assetId: $dividend->assetId,
                    walletId: $walletId,
                    exDate: $dividend->exDate->format('Y-m-d'),
                    quantity: $quantity,
                    amountPerShare: $dividend->amountPerShare,
                    amount: round($quantity * $dividend->amountPerShare, 2),
                );
            }
        }

        usort($receipts, fn (DividendReceiptData $a, DividendReceiptData $b): int => $b->exDate <=> $a->exDate);

        return $receipts;
    }

    /**
     * Quantité détenue la veille du détachement.
     *
     * La comparaison exclut l'ex-date : un achat passé ce jour-là ne donne pas droit au
     * dividende, seul un titre détenu avant le détachement le perçoit.
     *
     * @param  list<PositionRecordData>  $movements
     */
    private function quantityBefore(array $movements, Carbon $exDate): float
    {
        $quantity = 0.0;

        foreach ($movements as $movement) {
            if (! $movement->date->lt($exDate)) {
                continue;
            }

            $quantity += $movement->isSell ? -$movement->quantity : $movement->quantity;
        }

        return $quantity;
    }
}
