<?php

namespace App\Contexts\RealEstate\Support;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\RentException;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Services\PropertyWindowTotals;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use Illuminate\Support\Carbon;

/**
 * Traduit un bien (relations chargées) en données financières sur douze mois glissants. Les
 * fenêtres se comptent en mois d'échéance : de `today - 11 mois` (début de mois) à `today`.
 */
class PropertyFinancialsAssembler
{
    public function __construct(
        private RentScheduleCalculator $rents,
        private LoanAmortizationCalculator $amortization,
        private PropertyWindowTotals $totals,
    ) {}

    public function financialsFor(Property $property, Carbon $today): PropertyFinancialsData
    {
        $windowStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();
        $todayKey = $today->toDateString();

        $leases = $this->leaseTerms($property);
        $months = $this->rents->months($leases, $this->exceptions($property), $today);

        $rents12m = $this->totals->within(
            array_map(fn (RentMonthData $month): array => [
                'month' => $month->month,
                'amount' => $month->effective,
            ], $months),
            $windowStart,
            /**
             * Sans borne haute, à la différence des charges et des échéances : l'ancien code
             * n'en imposait aucune aux loyers (`if ($month->month >= $windowStart)`), et
             * `RentScheduleCalculator::months()` ne produit de toute façon rien après aujourd'hui.
             */
            '9999-12-31',
        );

        $expenses12m = $this->totals->within(
            $property->expenses
                ->map(fn (PropertyExpense $expense): array => [
                    'month' => $expense->date->toDateString(),
                    'amount' => (float) $expense->amount,
                ])
                ->values()
                ->all(),
            $windowStart,
            $todayKey,
        );

        $loanPayments12m = 0.0;
        $remaining = 0.0;
        $borrowed = 0.0;

        foreach ($property->loans as $loan) {
            $schedule = $this->scheduleFor($loan);
            $borrowed += (float) $loan->principal;
            $remaining += $this->amortization->remainingAt($schedule, $today);

            $loanPayments12m += $this->totals->within(
                array_map(fn (AmortizationLineData $line): array => [
                    'month' => $line->month,
                    'amount' => $line->payment,
                ], $schedule),
                $windowStart,
                $todayKey,
            );
        }

        $currentMonthlyRent = $this->rents->projectedAnnual($leases, $today) / 12;

        return new PropertyFinancialsData(
            acquisitionPrice: (float) $property->acquisition_price,
            acquisitionFees: (float) $property->acquisition_fees,
            currentMonthlyRent: round($currentMonthlyRent, 2),
            rents12m: round($rents12m, 2),
            expenses12m: round($expenses12m, 2),
            loanPayments12m: round($loanPayments12m, 2),
            borrowedPrincipal: round($borrowed, 2),
            remainingPrincipal: round($remaining, 2),
            currentValue: (float) ($property->valuations->last()?->value ?? 0.0),
        );
    }

    /** @return list<AmortizationLineData> */
    public function scheduleFor(Loan $loan): array
    {
        return $this->amortization->schedule(
            (float) $loan->principal,
            (float) $loan->annual_rate,
            $loan->term_months,
            $loan->start_date->copy(),
            (float) $loan->monthly_insurance,
        );
    }

    /** Capital restant dû sur un seul prêt, à une date — par opposition au total tous prêts confondus. */
    public function remainingFor(Loan $loan, Carbon $date): float
    {
        return $this->amortization->remainingAt($this->scheduleFor($loan), $date);
    }

    /** @return list<LeaseTermData> */
    public function leaseTerms(Property $property): array
    {
        return $property->leases
            ->map(fn (Lease $lease): LeaseTermData => new LeaseTermData(
                start: $lease->start_date->toDateString(),
                end: $lease->end_date?->toDateString(),
                monthlyRent: (float) $lease->monthly_rent,
            ))
            ->values()
            ->all();
    }

    /** @return list<RentExceptionData> */
    public function exceptions(Property $property): array
    {
        return $property->leases
            ->flatMap(fn (Lease $lease) => $lease->exceptions)
            ->map(fn (RentException $exception): RentExceptionData => new RentExceptionData(
                month: $exception->month->toDateString(),
                amountOverride: (float) $exception->amount_override,
            ))
            ->values()
            ->all();
    }
}
