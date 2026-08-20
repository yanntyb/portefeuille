<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\PropertyOverviewData;
use App\Contexts\RealEstate\Datas\RealEstateOverviewData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/** Carte immobilière du tableau de bord : patrimoine net par bien et totaux. */
class GetRealEstateOverview
{
    public function __construct(private PropertyFinancialsAssembler $assembler) {}

    public function __invoke(int $userId): RealEstateOverviewData
    {
        $properties = Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->orderBy('name')
            ->get();

        $today = Carbon::now();
        $lines = [];

        foreach ($properties as $property) {
            $financials = $this->assembler->financialsFor($property, $today);

            $lines[] = new PropertyOverviewData(
                id: $property->id,
                name: $property->name,
                currentValue: $financials->currentValue,
                remainingPrincipal: $financials->remainingPrincipal,
                netWorth: round($financials->currentValue - $financials->remainingPrincipal, 2),
                monthlyCashFlow: round(
                    ($financials->rents12m - $financials->expenses12m - $financials->loanPayments12m) / 12,
                    2,
                ),
            );
        }

        return new RealEstateOverviewData(
            properties: $lines,
            totalValue: round(array_sum(array_map(fn ($l): float => $l->currentValue, $lines)), 2),
            totalRemaining: round(array_sum(array_map(fn ($l): float => $l->remainingPrincipal, $lines)), 2),
            totalNetWorth: round(array_sum(array_map(fn ($l): float => $l->netWorth, $lines)), 2),
        );
    }
}
