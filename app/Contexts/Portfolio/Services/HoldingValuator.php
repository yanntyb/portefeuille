<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\HoldingLineData;

/**
 * La valorisation d'une position et son gain, à la ligne comme au total. Seul site de cette
 * formule : `PortfolioView` et `Wealth` la demandent plutôt que de la refaire.
 */
class HoldingValuator
{
    /**
     * Sans cours connu, une position vaut son prix de revient : le dernier prix qu'on lui connaisse
     * est celui qu'on a payé, comme un bien immobilier vaut son prix d'acquisition tant qu'aucune
     * réévaluation ne l'a touché.
     *
     * Rendre `null` faisait sortir la ligne de la valeur ET du coût, si bien qu'un actif ajouté
     * avant la première synchronisation des cours — ou un ticker délisté — pesait zéro partout.
     * Depuis que les apports nets se répartissent entre les classes, ce zéro s'est mis à inventer
     * une perte du montant de l'achat : l'apport était bien compté, la contrepartie non. Valeur et
     * coût redeviennent cohérents, et le gain est nul — le seul honnête faute de cotation.
     *
     * `null` reste la réponse quand il n'y a ni cours ni prix de revient : rien ne permet alors de
     * dire ce que la ligne vaut.
     *
     * @return array{marketValue: ?float, cost: ?float, gain: ?float, gainPct: ?float}
     */
    public function value(float $quantity, ?float $avgCost, ?float $lastPrice): array
    {
        $marketValue = match (true) {
            $lastPrice !== null => $quantity * $lastPrice,
            $avgCost !== null => $quantity * $avgCost,
            default => null,
        };

        $cost = ($avgCost !== null && $marketValue !== null) ? $quantity * $avgCost : null;
        $gain = ($marketValue !== null && $cost !== null) ? $marketValue - $cost : null;

        return [
            'marketValue' => $marketValue,
            'cost' => $cost,
            'gain' => $gain,
            'gainPct' => $this->pct($gain, $cost),
        ];
    }

    /**
     * Nul, et non zéro, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas
     * de pourcentage, et « 0 % » mentirait.
     */
    public function pct(?float $gain, ?float $cost): ?float
    {
        return ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;
    }

    /**
     * @param  list<HoldingLineData>  $lines
     * @return array{totalValue: float, totalCost: float, totalGain: float, totalGainPct: ?float}
     */
    public function totals(array $lines): array
    {
        $totalValue = 0.0;
        $totalCost = 0.0;
        $totalGain = 0.0;

        foreach ($lines as $line) {
            if ($line->marketValue !== null) {
                $totalValue += $line->marketValue;
            }

            if ($line->gain !== null && $line->avgCost !== null) {
                $totalCost += $line->quantity * $line->avgCost;
                $totalGain += $line->gain;
            }
        }

        return [
            'totalValue' => $totalValue,
            'totalCost' => $totalCost,
            'totalGain' => $totalGain,
            'totalGainPct' => $this->pct($totalGain, $totalCost),
        ];
    }
}
