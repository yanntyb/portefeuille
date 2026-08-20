<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Datas\PropertyMetricsData;

/** Indicateurs de rentabilité, tous avant impôt. Ratios en fraction, arrondis à 4 décimales. */
class PropertyMetricsCalculator
{
    public function metrics(PropertyFinancialsData $financials): PropertyMetricsData
    {
        $totalCost = $financials->acquisitionPrice + $financials->acquisitionFees;
        $downPayment = $totalCost - $financials->borrowedPrincipal;
        $annualCashFlow = round($financials->rents12m - $financials->expenses12m - $financials->loanPayments12m, 2);

        return new PropertyMetricsData(
            grossYield: $totalCost > 0 ? round($financials->currentMonthlyRent * 12 / $totalCost, 4) : null,
            netYield: $totalCost > 0 ? round(($financials->rents12m - $financials->expenses12m) / $totalCost, 4) : null,
            annualCashFlow: $annualCashFlow,
            cashOnCash: $downPayment > 0 ? round($annualCashFlow / $downPayment, 4) : null,
            ltv: $financials->currentValue > 0
                ? round($financials->remainingPrincipal / $financials->currentValue, 4)
                : null,
        );
    }
}
