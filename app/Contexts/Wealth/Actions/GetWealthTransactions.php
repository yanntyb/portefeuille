<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;

/**
 * L'historique des opérations du patrimoine, tous actifs confondus. Le tableau de bord le replie :
 * il compte l'historique entier, pas une fenêtre récente.
 */
class GetWealthTransactions
{
    public function __construct(private TransactionsPort $transactions) {}

    /** @return list<WealthTransactionLineData> */
    public function __invoke(int $userId): array
    {
        return $this->transactions->transactionsFor($userId);
    }
}
