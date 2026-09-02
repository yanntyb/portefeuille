<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WalletClassSliceData;
use App\Contexts\Wealth\Ports\AccountsPort;

/** Ce que l'enveloppe expose, classe par classe. Vide quand elle ne vaut rien. */
class GetWalletBreakdown
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WalletClassSliceData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->accounts->breakdownFor($userId, $walletId);
    }
}
