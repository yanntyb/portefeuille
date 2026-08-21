<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/**
 * Cash-flow mois par mois d'un bien : loyer encaissé, charges payées, échéance de prêt, et le net
 * qui reste. Le calcul était privé dans `GetPropertyDetail` ; trois appelants en ont désormais
 * besoin sur des fenêtres différentes, d'où l'extraction — la fenêtre est un argument, pas une
 * règle du service.
 */
class CashFlowCalculator
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private RentScheduleCalculator $rents,
    ) {}

    /**
     * Un élément par mois, de `$from` (ramené au premier du mois) au mois de `$until` inclus.
     *
     * @return list<MonthlyCashFlowData>
     */
    public function months(Property $property, Carbon $from, Carbon $until): array
    {
        $rentsByMonth = [];
        foreach ($this->rentMonths($property, $until) as $month) {
            $rentsByMonth[$month->month] = $month->effective;
        }

        $expensesByMonth = [];
        foreach ($property->expenses as $expense) {
            $key = $expense->date->copy()->startOfMonth()->toDateString();
            $expensesByMonth[$key] = ($expensesByMonth[$key] ?? 0.0) + (float) $expense->amount;
        }

        $paymentsByMonth = [];
        foreach ($property->loans as $loan) {
            foreach ($this->assembler->scheduleFor($loan) as $line) {
                $paymentsByMonth[$line->month] = ($paymentsByMonth[$line->month] ?? 0.0) + $line->payment;
            }
        }

        $flows = [];
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $until->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $key = $cursor->toDateString();
            $rents = $rentsByMonth[$key] ?? 0.0;
            $expenses = $expensesByMonth[$key] ?? 0.0;
            $payment = $paymentsByMonth[$key] ?? 0.0;

            $flows[] = new MonthlyCashFlowData(
                month: $key,
                rents: round($rents, 2),
                expenses: round($expenses, 2),
                loanPayment: round($payment, 2),
                net: round($rents - $expenses - $payment, 2),
            );

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $flows;
    }

    /** @return list<RentMonthData> */
    public function rentMonths(Property $property, Carbon $until): array
    {
        return $this->rents->months(
            $this->assembler->leaseTerms($property),
            $this->assembler->exceptions($property),
            $until,
        );
    }
}
