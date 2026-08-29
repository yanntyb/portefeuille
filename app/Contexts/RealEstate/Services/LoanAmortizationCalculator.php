<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use Illuminate\Support\Carbon;

/**
 * Échéancier français à mensualité constante : M = P·r ÷ (1 − (1 + r)⁻ⁿ), r = taux annuel ÷ 12.
 * Chaque ligne arrondit à deux décimales ; la dernière solde le capital exactement, si bien que
 * sa mensualité peut dévier de quelques centimes.
 */
class LoanAmortizationCalculator
{
    /** @return list<AmortizationLineData> */
    public function schedule(
        float $principal,
        float $annualRate,
        int $termMonths,
        Carbon $startDate,
        float $monthlyInsurance = 0.0,
    ): array {
        // Sans mensualité possible, pas d'échéancier : évite une division par zéro plus bas.
        if ($termMonths < 1) {
            return [];
        }

        $monthlyInsurance = round($monthlyInsurance, 2);
        $monthlyRate = $annualRate / 12;

        $basePayment = $monthlyRate === 0.0
            ? round($principal / $termMonths, 2)
            : round($principal * $monthlyRate / (1 - (1 + $monthlyRate) ** -$termMonths), 2);

        $lines = [];
        $remaining = $principal;

        for ($index = 1; $index <= $termMonths; $index++) {
            $interest = round($remaining * $monthlyRate, 2);

            $repaid = $index === $termMonths
                ? round($remaining, 2)
                : round($basePayment - $interest, 2);

            $remaining = round($remaining - $repaid, 2);

            $lines[] = new AmortizationLineData(
                month: $startDate->copy()->addMonthsNoOverflow($index)->startOfMonth()->toDateString(),
                payment: round($repaid + $interest + $monthlyInsurance, 2),
                interest: $interest,
                principal: $repaid,
                insurance: $monthlyInsurance,
                remaining: $remaining,
            );
        }

        return $lines;
    }

    /**
     * Capital restant dû à une date : celui de la dernière échéance passée, le total emprunté
     * avant la première, zéro après la dernière.
     *
     * @param  list<AmortizationLineData>  $schedule
     */
    public function remainingAt(array $schedule, Carbon $date): float
    {
        if ($schedule === []) {
            return 0.0;
        }

        $remaining = round($schedule[0]->remaining + $schedule[0]->principal, 2);

        foreach ($schedule as $line) {
            if ($line->month > $date->toDateString()) {
                break;
            }

            $remaining = $line->remaining;
        }

        return $remaining;
    }

    /**
     * Ce qu'un échéancier dit à une date : ce qui est réglé, ce qui reste. Une échéance compte dès
     * que son mois est entamé, celle du mois en cours comprise.
     *
     * @param  list<AmortizationLineData>  $schedule
     * @return array{monthlyPayment: float, endDate: string, monthsPaid: int, principalRepaid: float, interestPaid: float, interestRemaining: float, totalPaid: float}
     */
    public function summaryOf(array $schedule, string $todayLabel): array
    {
        $paid = array_filter(
            $schedule,
            fn (AmortizationLineData $line): bool => $line->month <= $todayLabel,
        );

        $interestPaid = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $paid));
        $interestTotal = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $schedule));

        return [
            'monthlyPayment' => $schedule[0]->payment,
            'endDate' => $schedule[count($schedule) - 1]->month,
            'monthsPaid' => count($paid),
            'principalRepaid' => round(array_sum(array_map(fn (AmortizationLineData $line): float => $line->principal, $paid)), 2),
            'interestPaid' => round($interestPaid, 2),
            'interestRemaining' => round($interestTotal - $interestPaid, 2),
            'totalPaid' => array_sum(array_map(fn (AmortizationLineData $line): float => $line->payment, $schedule)),
        ];
    }
}
