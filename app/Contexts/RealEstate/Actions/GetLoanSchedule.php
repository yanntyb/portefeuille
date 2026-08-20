<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;

/** Tableau d'amortissement complet, servi en prop différée sur la fiche du bien. */
class GetLoanSchedule
{
    public function __construct(private PropertyFinancialsAssembler $assembler) {}

    /** @return list<AmortizationLineData> */
    public function __invoke(int $userId, int $propertyId): array
    {
        $loan = Property::query()
            ->where('user_id', $userId)
            ->with('loans')
            ->find($propertyId)
            ?->loans
            ->first();

        return $loan === null ? [] : $this->assembler->scheduleFor($loan);
    }
}
