<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\UserProperties;

/**
 * Part immobilier de l'instantané hors-ligne : la page liste et une fiche par bien, props différées
 * comprises. `UserProperties` fournit l'énumération, comme aux trois autres actions du contexte —
 * une requête propre ici rouvrirait le N+1 qu'il existe pour fermer.
 */
class BuildRealEstateSnapshot
{
    public function __construct(
        private UserProperties $properties,
        private GetRealEstateOverview $overview,
        private BuildRealEstateSeries $series,
        private GetRealEstateProfitability $profitability,
        private GetRealEstateIncome $income,
        private GetPropertyDetail $getDetail,
        private GetLoanSchedule $getSchedule,
    ) {}

    /**
     * @return array{list: array<string, mixed>, byId: array<int, array<string, mixed>>}
     */
    public function __invoke(int $userId): array
    {
        $byId = [];

        foreach ($this->properties->forUser($userId) as $property) {
            /** @var Property $property */
            $detail = ($this->getDetail)($userId, $property->id);

            if ($detail === null) {
                continue;
            }

            $byId[$property->id] = [
                'property' => $detail,
                'amortization' => ($this->getSchedule)($userId, $property->id),
            ];
        }

        return [
            'list' => [
                'realEstate' => ($this->overview)($userId),
                'series' => ($this->series)($userId),
                'profitability' => ($this->profitability)($userId),
                'income' => ($this->income)($userId),
            ],
            'byId' => $byId,
        ];
    }
}
