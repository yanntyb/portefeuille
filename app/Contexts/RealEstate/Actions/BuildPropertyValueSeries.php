<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\PropertyValueSeriesData;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/**
 * Historique mensuel d'un bien, du mois d'acquisition à aujourd'hui. La valeur avance en escalier
 * — une estimation vaut jusqu'à la suivante — alors que le restant dû suit l'échéancier mois par
 * mois : les deux séries n'ont pas la même granularité naturelle, seul l'axe mensuel les réunit.
 */
class BuildPropertyValueSeries
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private LoanAmortizationCalculator $amortization,
    ) {}

    public function __invoke(int $userId, int $propertyId): PropertyValueSeriesData
    {
        $property = Property::query()
            ->where('user_id', $userId)
            ->with(['loans', 'valuations'])
            ->find($propertyId);

        if ($property === null) {
            return PropertyValueSeriesData::empty();
        }

        $valuations = $this->valuations($property);
        $schedules = $property->loans
            ->map(fn (Loan $loan): array => $this->assembler->scheduleFor($loan))
            ->all();

        $cursor = $property->acquisition_date->copy()->startOfMonth();
        $lastMonth = Carbon::now()->startOfMonth();

        $labels = [];
        $values = [];
        $remaining = [];

        /** Avant la première estimation, le bien ne vaut que ce qu'il a été payé. */
        $value = (float) $property->acquisition_price;

        while ($cursor->lessThanOrEqualTo($lastMonth)) {
            $monthEnd = $cursor->copy()->endOfMonth();

            while ($valuations !== [] && $valuations[0]['date'] <= $monthEnd->toDateString()) {
                $value = array_shift($valuations)['value'];
            }

            $labels[] = $cursor->toDateString();
            $values[] = round($value, 2);
            $remaining[] = round(array_sum(array_map(
                fn (array $schedule): float => $this->amortization->remainingAt($schedule, $monthEnd),
                $schedules,
            )), 2);

            $cursor = $cursor->addMonthNoOverflow();
        }

        return new PropertyValueSeriesData($labels, $values, $remaining);
    }

    /**
     * Estimations du plus ancien au plus récent : la boucle mensuelle les consomme dans cet ordre.
     *
     * @return list<array{date: string, value: float}>
     */
    private function valuations(Property $property): array
    {
        return $property->valuations
            ->sortBy(fn (PropertyValuation $valuation): string => $valuation->date->toDateString())
            ->map(fn (PropertyValuation $valuation): array => [
                'date' => $valuation->date->toDateString(),
                'value' => (float) $valuation->value,
            ])
            ->values()
            ->all();
    }
}
