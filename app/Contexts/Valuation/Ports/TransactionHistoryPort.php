<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Valuation\Datas\TransactionRecordData;

interface TransactionHistoryPort
{
    /** @return list<TransactionRecordData> */
    public function forUser(int $userId): array;
}
