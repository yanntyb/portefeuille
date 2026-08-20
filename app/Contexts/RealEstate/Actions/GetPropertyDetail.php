<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\ExpenseYearData;
use App\Contexts\RealEstate\Datas\LoanSummaryData;
use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Datas\PropertyDetailData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
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
            monthlyCashFlows: $this->monthlyCashFlows($property, $months, $today),
            rentHistory: array_reverse($months),
            expenseYears: $this->expenseYears($property),
            loan: $this->loanSummary($property, $today, $financials->remainingPrincipal),
        );
    }

    /**
     * @param  list<RentMonthData>  $months
     * @return list<MonthlyCashFlowData>
     */
    private function monthlyCashFlows(Property $property, array $months, Carbon $today): array
    {
        $windowStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();

        $rentsByMonth = [];
        foreach ($months as $month) {
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
        $cursor = Carbon::parse($windowStart);

        for ($index = 0; $index < 12; $index++) {
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

    /** @return list<ExpenseYearData> */
    private function expenseYears(Property $property): array
    {
        $years = [];

        foreach ($property->expenses as $expense) {
            $year = $expense->date->year;
            $category = $expense->category->value;
            $years[$year][$category] = ($years[$year][$category] ?? 0.0) + (float) $expense->amount;
        }

        krsort($years);

        return array_map(
            fn (int $year): ExpenseYearData => new ExpenseYearData(
                year: $year,
                byCategory: array_map(fn (float $amount): float => round($amount, 2), $years[$year]),
                total: round(array_sum($years[$year]), 2),
            ),
            array_keys($years),
        );
    }

    private function loanSummary(Property $property, Carbon $today, float $remaining): ?LoanSummaryData
    {
        $loan = $property->loans->first();

        if ($loan === null) {
            return null;
        }

        $schedule = $this->assembler->scheduleFor($loan);
        $totalPaid = array_sum(array_map(fn ($line): float => $line->payment, $schedule));

        return new LoanSummaryData(
            principal: (float) $loan->principal,
            annualRate: (float) $loan->annual_rate,
            termMonths: $loan->term_months,
            startDate: $loan->start_date->toDateString(),
            monthlyInsurance: (float) $loan->monthly_insurance,
            monthlyPayment: $schedule[0]->payment,
            remainingPrincipal: $remaining,
            totalCost: round($totalPaid - (float) $loan->principal, 2),
        );
    }
}
