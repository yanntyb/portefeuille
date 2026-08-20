<?php

namespace App\Contexts\Income\Sources\Rent\Infrastructure;

use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Adaptateur du contexte RealEstate : seuls les mois réellement encaissés (`effective > 0`)
 * deviennent des reçus — la vacance et l'impayé total n'ont rien produit.
 */
class RealEstateRentSchedule implements RentSchedulePort
{
    public function __construct(
        private RentScheduleCalculator $calculator,
        private PropertyFinancialsAssembler $assembler,
    ) {}

    /** @return list<RentReceiptData> */
    public function receiptsFor(int $userId): array
    {
        $receipts = [];
        $today = Carbon::now();

        foreach ($this->propertiesFor($userId) as $property) {
            $months = $this->calculator->months(
                $this->assembler->leaseTerms($property),
                $this->assembler->exceptions($property),
                $today,
            );

            foreach ($months as $month) {
                if ($month->effective > 0) {
                    $receipts[] = new RentReceiptData(
                        month: $month->month,
                        amount: $month->effective,
                        propertyName: $property->name,
                    );
                }
            }
        }

        return $receipts;
    }

    public function projectedAnnualFor(int $userId): float
    {
        $projected = 0.0;
        $today = Carbon::now();

        foreach ($this->propertiesFor($userId) as $property) {
            $projected += $this->calculator->projectedAnnual($this->assembler->leaseTerms($property), $today);
        }

        return round($projected, 2);
    }

    /** @return Collection<int, Property> */
    private function propertiesFor(int $userId): Collection
    {
        return Property::query()
            ->where('user_id', $userId)
            ->with('leases.exceptions')
            ->get();
    }
}
