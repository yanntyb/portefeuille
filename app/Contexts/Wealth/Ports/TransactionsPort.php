<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WealthTransactionLineData;

interface TransactionsPort
{
    /**
     * Les opérations de l'utilisateur, tous actifs confondus, la plus récente en tête.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsFor(int $userId): array;
}
