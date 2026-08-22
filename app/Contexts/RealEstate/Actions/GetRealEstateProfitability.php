<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\PropertyProfitabilityData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\PropertyMetricsCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Les indicateurs de rentabilité de chaque bien, ceux-là mêmes que porte sa fiche. La page du parc
 * les met côte à côte : une moyenne pondérée du parc cacherait le bien qui le plombe.
 */
class GetRealEstateProfitability
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private PropertyMetricsCalculator $metrics,
        private UserProperties $properties,
    ) {}

    /** @return list<PropertyProfitabilityData> */
    public function __invoke(int $userId): array
    {
        $today = Carbon::now();

        return $this->properties->forUser($userId)
            ->map(fn (Property $property): PropertyProfitabilityData => new PropertyProfitabilityData(
                id: $property->id,
                name: $property->name,
                metrics: $this->metrics->metrics($this->assembler->financialsFor($property, $today)),
            ))
            ->values()
            ->all();
    }
}
