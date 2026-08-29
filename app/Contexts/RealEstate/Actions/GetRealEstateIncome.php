<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Datas\RealEstateIncomeData;
use App\Contexts\RealEstate\Datas\RealEstateIncomeYearData;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Services\RollingWindow;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Les revenus locatifs du parc, tous biens confondus : les douze mois glissants pour la phrase de
 * tête, et une ligne par année civile pour les barres.
 *
 * Le net est déjà net de charges et d'échéances — c'est ce qui reste en poche, pas le loyer
 * encaissé. Un mois déficitaire tire donc son année vers le bas, et c'est voulu.
 */
class GetRealEstateIncome
{
    public function __construct(
        private CashFlowCalculator $cashFlows,
        private UserProperties $properties,
        private RollingWindow $window,
    ) {}

    public function __invoke(int $userId): RealEstateIncomeData
    {
        $properties = $this->properties->forUser($userId);

        if ($properties->isEmpty()) {
            return RealEstateIncomeData::empty();
        }

        $today = Carbon::now();
        $windowStart = $this->window->monthsFull($today);

        $window = ['rents' => 0.0, 'expenses' => 0.0, 'loanPayments' => 0.0];
        $byYear = [];

        foreach ($properties as $property) {
            $months = $this->cashFlows->months($property, $property->acquisition_date->copy(), $today);

            foreach ($months as $month) {
                $year = (int) substr($month->month, 0, 4);
                $byYear[$year] ??= ['rents' => 0.0, 'expenses' => 0.0, 'loanPayments' => 0.0];
                $this->add($byYear[$year], $month);

                if ($month->month >= $windowStart) {
                    $this->add($window, $month);
                }
            }
        }

        krsort($byYear);

        return new RealEstateIncomeData(
            rents12m: round($window['rents'], 2),
            expenses12m: round($window['expenses'], 2),
            loanPayments12m: round($window['loanPayments'], 2),
            net12m: round($window['rents'] - $window['expenses'] - $window['loanPayments'], 2),
            years: array_map(
                fn (int $year): RealEstateIncomeYearData => new RealEstateIncomeYearData(
                    year: $year,
                    rents: round($byYear[$year]['rents'], 2),
                    expenses: round($byYear[$year]['expenses'], 2),
                    loanPayments: round($byYear[$year]['loanPayments'], 2),
                    net: round(
                        $byYear[$year]['rents'] - $byYear[$year]['expenses'] - $byYear[$year]['loanPayments'],
                        2,
                    ),
                ),
                array_keys($byYear),
            ),
        );
    }

    /** @param array{rents: float, expenses: float, loanPayments: float} $totals */
    private function add(array &$totals, MonthlyCashFlowData $month): void
    {
        $totals['rents'] += $month->rents;
        $totals['expenses'] += $month->expenses;
        $totals['loanPayments'] += $month->loanPayment;
    }
}
