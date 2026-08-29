<?php

namespace App\Contexts\Portfolio\Services;

/**
 * La valeur du portefeuille répartie entre secteurs. Les poids d'un actif sont normalisés avant
 * répartition : un fournisseur qui rend 0,6 et 0,5 décrit des parts, pas des fractions de un.
 *
 * La clé de repli est passée par l'appelant : `Sector` appartient à `Market`, et ce calculateur
 * ne connaît que des chaînes.
 */
class SectorSplitter
{
    /**
     * @param  array<int, float>  $valueByAsset
     * @param  array<int, array<string, float>>  $weightsByAsset  Poids bruts par clé de secteur.
     * @return list<array{sector: string, value: float, pct: float}>
     */
    public function split(array $valueByAsset, array $weightsByAsset, string $fallback): array
    {
        if ($valueByAsset === []) {
            return [];
        }

        $total = array_sum($valueByAsset);

        /** @var array<string, float> $valueBySector */
        $valueBySector = [];

        foreach ($valueByAsset as $assetId => $value) {
            $weights = $weightsByAsset[$assetId] ?? [];
            $totalWeight = array_sum($weights);

            if ($totalWeight <= 0.0) {
                $valueBySector[$fallback] = ($valueBySector[$fallback] ?? 0.0) + $value;

                continue;
            }

            foreach ($weights as $sector => $weight) {
                $valueBySector[$sector] = ($valueBySector[$sector] ?? 0.0) + $value * ($weight / $totalWeight);
            }
        }

        arsort($valueBySector);

        $slices = [];

        foreach ($valueBySector as $sector => $value) {
            $slices[] = [
                'sector' => $sector,
                'value' => $value,
                'pct' => $total > 0.0 ? $value / $total * 100 : 0.0,
            ];
        }

        return $slices;
    }
}
