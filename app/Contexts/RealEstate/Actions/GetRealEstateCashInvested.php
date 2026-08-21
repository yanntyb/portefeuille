<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Le cash réellement sorti de la poche pour l'immobilier : l'apport, plus chaque mois que le bien
 * n'a pas couvert de lui-même.
 *
 * Ce n'est pas `apport + capital remboursé`. Cette formule-là compte le capital deux fois dès
 * qu'un mois est déficitaire — l'échéance qui sort de la poche le contient déjà — et elle compte
 * comme une mise le capital remboursé par le locataire, qui n'a rien coûté. C'est ce capital-là
 * qui doit apparaître en gain : c'est le levier.
 */
class GetRealEstateCashInvested
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
        return round($this->downPaymentFor($property) + $this->injected($property, $today), 2);
    }

    /**
     * Négatif quand l'emprunt dépasse le coût d'acquisition : un financement à plus de 100 %.
     *
     * Publique parce que `BuildRealEstateSeries` en a besoin seule : elle cumule les injections
     * label par label et ne peut pas se servir de `forProperty()`, qui les cumule déjà.
     */
    public function downPaymentFor(Property $property): float
    {
        $borrowed = $property->loans->sum(fn (Loan $loan): float => (float) $loan->principal);

        return (float) $property->acquisition_price + (float) $property->acquisition_fees - $borrowed;
    }

    /** Seuls les mois déficitaires injectent : un mois excédentaire rend du cash, il ne le prend pas. */
    private function injected(Property $property, Carbon $today): float
    {
        $from = $property->acquisition_date->copy()->startOfMonth();

        if ($from > $today) {
            return 0.0;
        }

        $injected = 0.0;

        foreach ($this->cashFlows->months($property, $from, $today) as $flow) {
            $injected += max(0.0, -$flow->net);
        }

        return $injected;
    }
}
