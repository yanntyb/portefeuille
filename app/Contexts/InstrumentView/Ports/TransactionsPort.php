<?php

namespace App\Contexts\InstrumentView\Ports;

use App\Contexts\InstrumentView\Datas\TransactionLineData;

interface TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;
}
