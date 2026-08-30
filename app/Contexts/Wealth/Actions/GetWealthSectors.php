<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthSectorData;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;

/**
 * Le patrimoine ventilé par secteur, toutes classes confondues. Chaque classe dit à quoi elle
 * expose son porteur — les secteurs boursiers pour un portefeuille, une seule tranche pour un parc
 * immobilier —, et seule cette action rapporte ces montants au patrimoine entier.
 */
class GetWealthSectors
{
    public function __construct(private AssetClassRegistry $classes) {}

    /** @return list<WealthSectorData> */
    public function __invoke(int $userId): array
    {
        /** @var array<string, float> $valueByLabel */
        $valueByLabel = [];
        $total = 0.0;

        foreach ($this->classes->all() as $class) {
            foreach ($class->sectorSlicesFor($userId) as $slice) {
                /** Une tranche sans valeur n'apprend rien : elle ne prend pas de ligne. */
                if ($slice->value <= 0.0) {
                    continue;
                }

                $valueByLabel[$slice->label] = ($valueByLabel[$slice->label] ?? 0.0) + $slice->value;
                $total += $slice->value;
            }
        }

        if ($total <= 0.0) {
            return [];
        }

        arsort($valueByLabel);

        return array_values(array_map(
            fn (string $label): WealthSectorData => new WealthSectorData(
                label: $label,
                value: round($valueByLabel[$label], 2),
                pct: round($valueByLabel[$label] / $total * 100, 2),
            ),
            array_keys($valueByLabel),
        ));
    }
}
