<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use App\Contexts\RealEstate\Datas\ExpenseYearData;
use App\Contexts\RealEstate\Datas\LoanSummaryData;
use App\Contexts\RealEstate\Datas\PropertyDetailData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Services\PropertyMetricsCalculator;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/** Fiche complète d'un bien : indicateurs, cash-flow, loyers, charges, prêt. */
class GetPropertyDetail
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private RentScheduleCalculator $rents,
        private PropertyMetricsCalculator $metrics,
        private CashFlowCalculator $cashFlows,
    ) {}

    public function __invoke(int $userId, int $propertyId): ?PropertyDetailData
    {
        $property = Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->find($propertyId);

        if ($property === null) {
            return null;
        }

        $today = Carbon::now();
        $financials = $this->assembler->financialsFor($property, $today);
        $months = $this->rents->months(
            $this->assembler->leaseTerms($property),
            $this->assembler->exceptions($property),
            $today,
        );

        return new PropertyDetailData(
            id: $property->id,
            name: $property->name,
            address: $property->address,
            acquisitionDate: $property->acquisition_date->toDateString(),
            acquisitionPrice: (float) $property->acquisition_price,
            acquisitionFees: (float) $property->acquisition_fees,
            currentValue: $financials->currentValue,
            netWorth: round($financials->currentValue - $financials->remainingPrincipal, 2),
            metrics: $this->metrics->metrics($financials),
            monthlyCashFlows: $this->cashFlows->months(
                $property,
                $this->cashFlowWindowStart($months, $today),
                $today,
            ),
            rentHistory: array_reverse($months),
            expenseYears: $this->expenseYears($property),
            loan: $this->loanSummary($property, $today),
        );
    }

    /**
     * Premier mois du cash-flow : douze mois glissants au moins, et tout l'historique des loyers
     * quand le bail est plus ancien — chaque loyer se lit avec son net en regard.
     *
     * @param  list<RentMonthData>  $months  Ordre chronologique.
     */
    private function cashFlowWindowStart(array $months, Carbon $today): Carbon
    {
        $slidingStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11);

        if ($months === []) {
            return $slidingStart;
        }

        $firstRentMonth = Carbon::parse($months[0]->month);

        return $firstRentMonth < $slidingStart ? $firstRentMonth : $slidingStart;
    }

    /** @return list<ExpenseYearData> */
    private function expenseYears(Property $property): array
    {
        $years = [];

        foreach ($property->expenses as $expense) {
            $year = $expense->date->year;
            $category = $expense->category->value;
            $years[$year][$category] ??= ['label' => $expense->category->getLabel(), 'amount' => 0.0];
            $years[$year][$category]['amount'] += (float) $expense->amount;
        }

        krsort($years);

        return array_map(
            fn (int $year): ExpenseYearData => new ExpenseYearData(
                year: $year,
                byCategory: $this->byCategory($years[$year]),
                total: round(array_sum(array_column($years[$year], 'amount')), 2),
            ),
            array_keys($years),
        );
    }

    /**
     * Ventilation d'une année triée par montant décroissant : la plus grosse charge en tête, à
     * égalité l'ordre suit la première dépense rencontrée (tri stable).
     *
     * @param  array<string, array{label: string, amount: float}>  $amounts  Clé : valeur d'`ExpenseCategory`.
     * @return list<array{category: string, label: string, amount: float}>
     */
    private function byCategory(array $amounts): array
    {
        $entries = [];

        foreach ($amounts as $category => $entry) {
            $entries[] = [
                'category' => $category,
                'label' => $entry['label'],
                'amount' => round($entry['amount'], 2),
            ];
        }

        usort($entries, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return $entries;
    }

    private function loanSummary(Property $property, Carbon $today): ?LoanSummaryData
    {
        $loan = $property->loans->first();

        if ($loan === null) {
            return null;
        }

        $schedule = $this->assembler->scheduleFor($loan);
        $totalPaid = array_sum(array_map(fn (AmortizationLineData $line): float => $line->payment, $schedule));

        /** Une échéance est réglée dès que son mois est entamé : celle du mois en cours compte. */
        $paid = array_filter(
            $schedule,
            fn (AmortizationLineData $line): bool => $line->month <= $today->toDateString(),
        );

        $interestPaid = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $paid));
        $interestTotal = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $schedule));

        return new LoanSummaryData(
            principal: (float) $loan->principal,
            annualRate: (float) $loan->annual_rate,
            termMonths: $loan->term_months,
            startDate: $loan->start_date->toDateString(),
            monthlyInsurance: (float) $loan->monthly_insurance,
            monthlyPayment: $schedule[0]->payment,
            remainingPrincipal: $this->assembler->remainingFor($loan, $today),
            totalCost: round($totalPaid - (float) $loan->principal, 2),
            endDate: $schedule[count($schedule) - 1]->month,
            monthsPaid: count($paid),
            principalRepaid: round(array_sum(array_map(fn (AmortizationLineData $line): float => $line->principal, $paid)), 2),
            interestPaid: round($interestPaid, 2),
            interestRemaining: round($interestTotal - $interestPaid, 2),
        );
    }
}
