<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\ValuationPort;

/** La valeur d'une enveloppe dans le temps, comparée à ce qui y a été mis. */
class GetWalletSeries
{
    public function __construct(private ValuationPort $valuation) {}

    public function __invoke(int $userId, int $walletId): ClassSeriesData
    {
        return $this->valuation->seriesForWallet($userId, $walletId);
    }
}
