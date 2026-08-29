<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Les enveloppes d'un même actif ramenées à une position unique. Le prix de revient d'un actif
 * tenu dans deux enveloppes est la moyenne pondérée des leurs ; celles qui n'en déclarent aucun
 * comptent dans la quantité mais pas dans la moyenne.
 */
class PositionAggregator
{
    /**
     * @param  list<array{quantity: float, avgCost: ?float}>  $rows
     * @return array{quantity: float, avgCost: ?float}
     */
    public function __invoke(array $rows): array
    {
        $quantity = 0.0;
        $qtyWithCost = 0.0;
        $weighted = 0.0;

        foreach ($rows as $row) {
            $quantity += $row['quantity'];

            if ($row['avgCost'] !== null) {
                $qtyWithCost += $row['quantity'];
                $weighted += $row['quantity'] * $row['avgCost'];
            }
        }

        return [
            'quantity' => $quantity,
            'avgCost' => $qtyWithCost > 0.0 ? $weighted / $qtyWithCost : null,
        ];
    }
}
