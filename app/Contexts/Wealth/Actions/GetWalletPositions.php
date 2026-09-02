<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthHoldingData;
use App\Contexts\Wealth\Ports\AccountsPort;

/** Les positions tenues dans une enveloppe, toutes classes confondues. */
class GetWalletPositions
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WealthHoldingData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->accounts->positionsFor($userId, $walletId);
    }
}
