<?php

namespace App\Contexts\InstrumentView\Ports;

interface TransactionsPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;
}
