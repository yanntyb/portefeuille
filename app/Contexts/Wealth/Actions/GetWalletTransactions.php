<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;

/** L'historique d'une enveloppe, la plus récente en tête. */
class GetWalletTransactions
{
    public function __construct(private TransactionsPort $transactions) {}

    /** @return list<WealthTransactionLineData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->transactions->transactionsForWallet($userId, $walletId);
    }
}
