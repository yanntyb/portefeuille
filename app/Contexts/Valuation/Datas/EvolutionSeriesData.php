<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class EvolutionSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $value
     * @param  list<float>  $totalInvested
     * @param  list<AssetInvestedSeriesData>  $perAsset
     */
    public function __construct(
        public array $labels,
        public array $value,
        public array $totalInvested,
        public array $perAsset,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'value' => $this->value,
            'totalInvested' => $this->totalInvested,
            'perAsset' => $this->perAsset,
        ];
    }
}
