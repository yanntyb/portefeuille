<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

/**
 * Le journal des opérations ramené à un périmètre. Seul site de ce filtrage : `BuildExposureSeries`
 * et `BuildPortfolioPerformances` en portaient jusqu'ici deux copies mot pour mot, avec le risque
 * d'en corriger une seule.
 *
 * Les deux filtres ne se comportent pas pareil, à dessein :
 *
 * - **par classe**, tout mouvement sans `asset_id` passe — un versement, un retrait ou un dividende
 *   n'appartient à aucune exposition, et l'écarter bâtirait un cash sur les seuls achats et ventes
 *   de la classe, négatif en permanence puisqu'il ne verrait jamais les versements qui les ont
 *   financés ;
 * - **par enveloppe**, le cash est écarté franchement : il est tenu par compte, et attribuer un
 *   versement aux comptes voisins gonflerait leur apport d'un argent qu'ils n'ont jamais vu.
 *
 * Ne pas aligner l'un sur l'autre.
 */
class ScopedTransactions
{
    public function __construct(private InstrumentDirectoryPort $directory) {}

    /**
     * @param  list<TransactionRecordData>  $transactions
     * @return list<TransactionRecordData>
     */
    public function within(array $transactions, HoldingScope $scope): array
    {
        if ($scope->classes !== null) {
            $kept = array_flip($this->directory->idsOfClasses($scope->classes));

            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $transaction->assetId === null
                    || isset($kept[$transaction->assetId]),
            ));
        }

        if ($scope->walletId !== null) {
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $scope->admitsWallet($transaction->walletId),
            ));
        }

        return $transactions;
    }
}
