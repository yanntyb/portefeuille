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

    /**
     * Cash injecté mois par mois depuis l'acquisition, indexé par premier jour du mois. Seuls les
     * mois déficitaires y figurent : un mois excédentaire rend du cash, il n'en prend pas.
     *
     * Cette règle vit ici et nulle part ailleurs — le cash sorti d'un bien et la série de son
     * patrimoine net la lisaient tous les deux, chacun avec sa copie.
     *
     * @return array<string, float>
     */
    public function injectionsSince(Property $property, Carbon $until): array
    {
        return $this->netsSince($property, $until, -1.0);
    }

    /**
     * Cash rendu mois par mois depuis l'acquisition, indexé par premier jour du mois. Miroir exact
     * de `injectionsSince()` : seuls les mois excédentaires y figurent.
     *
     * Un mois ne peut pas peupler les deux tableaux, et leur union couvre tous les mois non nuls :
     * c'est ce qui autorise à compter le rendu en gain sans jamais recouper la mise.
     *
     * @return array<string, float>
     */
    public function surplusesSince(Property $property, Carbon $until): array
    {
        return $this->netsSince($property, $until, 1.0);
    }

    /**
     * Les nets du signe demandé, en valeur absolue. `$sign` vaut −1 pour les mois déficitaires,
     * 1 pour les excédentaires.
     *
     * @return array<string, float>
     */
    private function netsSince(Property $property, Carbon $until, float $sign): array
    {
        $from = $property->acquisition_date->copy()->startOfMonth();

        if ($from > $until) {
            return [];
        }

        $nets = [];

        foreach ($this->months($property, $from, $until) as $flow) {
            $net = max(0.0, $sign * $flow->net);

            if ($net > 0.0) {
                $nets[$flow->month] = $net;
            }
        }

        return $nets;
    }
}
