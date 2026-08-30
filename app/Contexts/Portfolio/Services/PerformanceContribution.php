<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\ContributionData;

/**
 * Ce que chaque position apporte au rendement de l'exposition, en points de ce rendement.
 *
 * Le dénominateur est la valeur totale de l'exposition, jamais le gain total. « Quelle part du
 * gain vient de cette ligne » paraît plus direct mais s'effondre dès que l'exposition perd : le
 * dénominateur devient négatif, et une position gagnante afficherait une contribution négative.
 * Rapportée à la valeur, la mesure garde son sens dans les deux cas.
 */
class PerformanceContribution
{
    /**
     * @param  list<array{assetId: int, assetName: string, gain: ?float, marketValue: ?float}>  $positions
     * @return list<ContributionData>
     */
    public function of(array $positions, float $totalValue): array
    {
        /** Une position dont le gain est inconnu ne contribue pas de zéro : elle ne se mesure pas. */
        $measurable = array_values(array_filter(
            $positions,
            fn (array $position): bool => $position['gain'] !== null,
        ));

        $lines = array_map(fn (array $position): ContributionData => new ContributionData(
            assetId: $position['assetId'],
            assetName: $position['assetName'],
            contribution: $totalValue > 0.0 ? round($position['gain'] / $totalValue * 100, 2) : null,
            weight: ($totalValue > 0.0 && $position['marketValue'] !== null)
                ? round($position['marketValue'] / $totalValue * 100, 2)
                : null,
        ), $measurable);

        usort($lines, fn (ContributionData $a, ContributionData $b): int => ($b->contribution ?? 0.0) <=> ($a->contribution ?? 0.0));

        return $lines;
    }
}
