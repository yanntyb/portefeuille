<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Ports\IncomePort;
use App\Contexts\Wealth\Ports\RealEstatePort;

/**
 * Ce que le patrimoine laisse chaque mois. Les dividendes sont mensualisés sur douze mois
 * glissants, le locatif est déjà net de charges et d'échéances.
 */
class GetWealthIncome
{
    public function __construct(
        private IncomePort $income,
        private RealEstatePort $realEstate,
    ) {}

    public function __invoke(int $userId): WealthIncomeData
    {
        $dividends = $this->income->monthlyDividendsFor($userId);
        $rentalNet = $this->realEstate->monthlyNetFor($userId);

        return new WealthIncomeData(
            monthlyTotal: round($dividends + $rentalNet, 2),
            monthlyDividends: $dividends,
            monthlyRentalNet: $rentalNet,
        );
    }
}
