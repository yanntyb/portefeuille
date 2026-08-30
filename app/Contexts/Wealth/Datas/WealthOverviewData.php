<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Le patrimoine d'un utilisateur : son total, et ses classes d'actif dans l'ordre du registre.
 * Cet ordre est celui que le tableau de bord affiche — il vient de la déclaration, pas d'un tri.
 */
readonly class WealthOverviewData implements JsonSerializable
{
    /** @param  list<AssetClassData>  $classes */
    public function __construct(
        public float $totalValue,
        public float $totalInvested,
        public float $totalGain,
        public ?float $totalGainPct,
        public float $totalRealizedGain,
        public array $classes,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalInvested' => $this->totalInvested,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'totalRealizedGain' => $this->totalRealizedGain,
            'classes' => array_map(fn (AssetClassData $class): array => $class->jsonSerialize(), $this->classes),
        ];
    }
}
