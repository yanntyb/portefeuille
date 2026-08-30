<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Le cash rentré dans la poche par l'immobilier : chaque mois que le bien a couvert au-delà de ses
 * charges et de son échéance.
 *
 * Miroir de `GetRealEstateCashInvested`, qui compte les mois déficitaires comme une mise. Sans lui,
 * un bien déficitaire pesait sur le gain tandis qu'un bien excédentaire n'y apportait rien : le
 * loyer encaissé disparaissait du patrimoine. Aucun mois ne peut compter des deux côtés, la mise
 * n'est donc jamais recoupée.
 */
class GetRealEstateCashReturned
{
    public function __construct(
        private UserProperties $properties,
        private CashFlowCalculator $cashFlows,
    ) {}

    public function __invoke(int $userId): float
    {
        $today = Carbon::now();
        $total = 0.0;

        foreach ($this->properties->forUser($userId) as $property) {
            $total += $this->forProperty($property, $today);
        }

        return round($total, 2);
    }

    /** Relations `loans`, `leases.exceptions` et `expenses` attendues chargées. */
    public function forProperty(Property $property, Carbon $today): float
    {
        return round(array_sum($this->cashFlows->surplusesSince($property, $today)), 2);
    }
}
