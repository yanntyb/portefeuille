<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\ExpenseYearData;
use App\Contexts\RealEstate\Datas\LoanSummaryData;
use App\Contexts\RealEstate\Datas\PropertyDetailData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Services\ExpenseGrouper;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
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
        private ExpenseGrouper $expenseGrouper,
        private LoanAmortizationCalculator $amortization,
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
        $grouped = $this->expenseGrouper->byYear(
            $property->expenses
                ->map(fn (PropertyExpense $expense): array => [
                    'year' => $expense->date->year,
                    'category' => $expense->category->value,
                    'label' => $expense->category->getLabel(),
                    'amount' => (float) $expense->amount,
                ])
                ->values()
                ->all(),
        );

        return array_map(fn (array $year): ExpenseYearData => new ExpenseYearData(
            year: $year['year'],
            byCategory: $year['byCategory'],
            total: $year['total'],
        ), $grouped);
    }

    private function loanSummary(Property $property, Carbon $today): ?LoanSummaryData
    {
        $loan = $property->loans->first();

        if ($loan === null) {
            return null;
        }

        $schedule = $this->assembler->scheduleFor($loan);
        $summary = $this->amortization->summaryOf($schedule, $today->toDateString());

        return new LoanSummaryData(
            principal: (float) $loan->principal,
            annualRate: (float) $loan->annual_rate,
            termMonths: $loan->term_months,
            startDate: $loan->start_date->toDateString(),
            monthlyInsurance: (float) $loan->monthly_insurance,
            monthlyPayment: $summary['monthlyPayment'],
            remainingPrincipal: $this->assembler->remainingFor($loan, $today),
            totalCost: round($summary['totalPaid'] - (float) $loan->principal, 2),
            endDate: $summary['endDate'],
            monthsPaid: $summary['monthsPaid'],
            principalRepaid: $summary['principalRepaid'],
            interestPaid: $summary['interestPaid'],
            interestRemaining: $summary['interestRemaining'],
        );
    }
}
