<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use App\Contexts\RealEstate\Datas\RealEstateSeriesData;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Patrimoine net immobilier semaine par semaine, et le cash investi en regard.
 *
 * La grille n'est pas alignée sur celle des titres : c'est `Wealth\Services\SeriesAligner` qui
 * réconcilie les deux. Un bien acheté avant la première transaction du portefeuille aurait sinon
 * été tronqué, et un utilisateur sans aucune transaction n'aurait pas eu un seul label.
 */
class BuildRealEstateSeries
{
    public function __construct(
        private UserProperties $properties,
        private CashFlowCalculator $cashFlows,
        private PropertyFinancialsAssembler $assembler,
        private LoanAmortizationCalculator $amortization,
        private GetRealEstateCashInvested $cashInvested,
    ) {}

    public function __invoke(int $userId): RealEstateSeriesData
    {
        $properties = $this->properties->forUser($userId);

        if ($properties->isEmpty()) {
            return RealEstateSeriesData::empty();
        }

        $today = Carbon::now();
        $labels = $this->weeklyLabels($properties->min('acquisition_date'), $today);

        $netWorth = array_fill(0, count($labels), 0.0);
        $invested = array_fill(0, count($labels), 0.0);

        foreach ($properties as $property) {
            $acquisition = $property->acquisition_date->toDateString();
            $valuations = $this->valuationPoints($property);
            $schedules = $this->schedulesFor($property);
            $injections = $this->injectionsByMonth($property, $today);
            $downPayment = $this->cashInvested->downPaymentFor($property);

            foreach ($labels as $index => $label) {
                if ($label < $acquisition) {
                    continue;
                }

                $netWorth[$index] += $this->valueAt($valuations, $label) - $this->remainingAt($schedules, $label);
                $invested[$index] += $downPayment + $this->injectedUpTo($injections, $label);
            }
        }

        return new RealEstateSeriesData(
            labels: $labels,
            netWorth: array_map(fn (float $amount): float => round($amount, 2), $netWorth),
            invested: array_map(fn (float $amount): float => round($amount, 2), $invested),
        );
    }

    /**
     * Tous les lundis depuis celui qui précède la plus ancienne acquisition, puis aujourd'hui —
     * sans quoi le dernier point de la série serait vieux de six jours au plus mauvais moment.
     *
     * @return list<string>
     */
    private function weeklyLabels(Carbon $firstAcquisition, Carbon $today): array
    {
        $cursor = $firstAcquisition->copy()->startOfWeek();
        $labels = [];

        while ($cursor < $today) {
            $labels[] = $cursor->toDateString();
            $cursor = $cursor->addWeek();
        }

        $labels[] = $today->toDateString();

        return $labels;
    }

    /**
     * Valuations triées par date croissante, en paires `[date, valeur]`.
     *
     * @return list<array{0: string, 1: float}>
     */
    private function valuationPoints(Property $property): array
    {
        return $property->valuations
            ->sortBy(fn (PropertyValuation $valuation): string => $valuation->date->toDateString())
            ->map(fn (PropertyValuation $valuation): array => [
                $valuation->date->toDateString(),
                (float) $valuation->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Escalier : la dernière valeur estimée de date ≤ au label, 0 avant la première. Aucune
     * interpolation — une valeur n'est connue que le jour où elle a été estimée.
     *
     * @param  list<array{0: string, 1: float}>  $points
     */
    private function valueAt(array $points, string $label): float
    {
        $value = 0.0;

        foreach ($points as [$date, $amount]) {
            if ($date > $label) {
                break;
            }

            $value = $amount;
        }

        return $value;
    }

    /** @return list<list<AmortizationLineData>> */
    private function schedulesFor(Property $property): array
    {
        return $property->loans
            ->map(fn (Loan $loan): array => $this->assembler->scheduleFor($loan))
            ->values()
            ->all();
    }

    /** @param  list<list<AmortizationLineData>>  $schedules */
    private function remainingAt(array $schedules, string $label): float
    {
        $remaining = 0.0;

        foreach ($schedules as $schedule) {
            $remaining += $this->amortization->remainingAt($schedule, Carbon::parse($label));
        }

        return $remaining;
    }

    /**
     * Cash injecté par mois, indexé par premier jour du mois. Seuls les mois déficitaires y
     * figurent.
     *
     * @return array<string, float>
     */
    private function injectionsByMonth(Property $property, Carbon $today): array
    {
        $from = $property->acquisition_date->copy()->startOfMonth();

        if ($from > $today) {
            return [];
        }

        $injections = [];

        foreach ($this->cashFlows->months($property, $from, $today) as $flow) {
            $injected = max(0.0, -$flow->net);

            if ($injected > 0.0) {
                $injections[$flow->month] = $injected;
            }
        }

        return $injections;
    }

    /** @param  array<string, float>  $injections */
    private function injectedUpTo(array $injections, string $label): float
    {
        $total = 0.0;

        foreach ($injections as $month => $amount) {
            if ($month <= $label) {
                $total += $amount;
            }
        }

        return $total;
    }
}
