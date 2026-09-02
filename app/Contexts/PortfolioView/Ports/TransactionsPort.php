<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\ClassTransactionLineData;
use App\Contexts\PortfolioView\Datas\TransactionLineData;

interface TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;

    /**
     * L'historique d'une exposition entière, chaque ligne nommant son actif : hors d'une fiche,
     * une quantité ne dit pas de quoi elle est la quantité.
     *
     * @return list<ClassTransactionLineData>
     */
    public function transactionsForClass(int $userId, AssetClass $exposure): array;
}
