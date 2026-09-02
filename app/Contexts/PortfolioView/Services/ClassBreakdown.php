<?php

namespace App\Contexts\PortfolioView\Services;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\PortfolioView\Datas\ClassSliceData;

/**
 * La répartition par classe d'actif des lignes d'une enveloppe : une part par classe, la plus
 * lourde en tête. Une ligne sans valeur de marché compte pour zéro.
 */
class ClassBreakdown
{
    /**
     * @param  list<HoldingLineData>  $lines
     * @return list<ClassSliceData>
     */
    public function of(array $lines): array
    {
        $byClass = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $value = $line->marketValue ?? 0.0;
            $byClass[$line->assetClass->value] = ($byClass[$line->assetClass->value] ?? 0.0) + $value;
            $total += $value;
        }

        if ($total <= 0.0) {
            return [];
        }

        $slices = array_map(
            fn (string $class, float $value): ClassSliceData => new ClassSliceData(
                key: $class,
                label: AssetClass::from($class)->getLabel(),
                value: $value,
                share: $value / $total * 100,
            ),
            array_keys($byClass),
            array_values($byClass),
        );

        usort(
            $slices,
            fn (ClassSliceData $left, ClassSliceData $right): int => $right->value <=> $left->value,
        );

        return $slices;
    }
}
