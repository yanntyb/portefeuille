<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * L'en-tête d'une enveloppe : ce qu'elle vaut, et les règles qu'elle déclare. `null` quand elle
 * n'existe pas ou qu'un autre porteur la tient — la page en fait un 404, sans dire lequel des deux
 * cas elle a rencontré.
 */
class GetWalletAccount
{
    public function __construct(private AccountsPort $accounts) {}

    public function __invoke(int $userId, int $walletId): ?WealthAccountData
    {
        return $this->accounts->accountFor($userId, $walletId);
    }
}
