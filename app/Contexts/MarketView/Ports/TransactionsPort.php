<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\TransactionLineData;

interface TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;
}
