<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * Les enveloppes de détention du patrimoine. Le tableau de bord les replie : c'est la lecture
 * fiscale, pas le grand chiffre.
 */
class GetWealthAccounts
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WealthAccountData> */
    public function __invoke(int $userId): array
    {
        return $this->accounts->accountsFor($userId);
    }
}
