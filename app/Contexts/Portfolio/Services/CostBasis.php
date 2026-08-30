<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Le prix de revient d'un flux d'achats. Sans achat, la moyenne vaut zéro plutôt que nul : c'est
 * ce qu'attendent la projection d'une position et le calcul d'un gain réalisé, où l'absence
 * d'achat antérieur vaut un coût nul.
 *
 * Les frais d'achat entrent dans le coût : ce que la position a réellement coûté, c'est ce qui est
 * sorti du compte, courtage compris. La courbe « investi » les comptait déjà, pas le prix de
 * revient — les deux disaient deux montants différents de la même chose.
 */
class CostBasis
{
    /**
     * @param  list<array{quantity: float, unitPrice: float, fees?: float}>  $buys
     * @return array{quantity: float, cost: float, average: float}
     */
    public function of(array $buys): array
    {
        $quantity = 0.0;
        $cost = 0.0;

        foreach ($buys as $buy) {
            $quantity += $buy['quantity'];
            $cost += $buy['quantity'] * $buy['unitPrice'] + ($buy['fees'] ?? 0.0);
        }

        return [
            'quantity' => $quantity,
            'cost' => $cost,
            'average' => $quantity > 0.0 ? $cost / $quantity : 0.0,
        ];
    }
}
