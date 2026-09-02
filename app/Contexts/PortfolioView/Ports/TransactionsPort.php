<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\ClassTransactionLineData;
use App\Contexts\PortfolioView\Datas\TransactionLineData;

interface TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;

    /**
     * L'historique d'un périmètre entier — une exposition, une enveloppe —, chaque ligne nommant
     * son actif : hors d'une fiche, une quantité ne dit pas de quoi elle est la quantité.
     *
     * @return list<ClassTransactionLineData>
     */
    public function transactionsForScope(int $userId, HoldingScope $scope): array;
}
