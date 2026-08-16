<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class EvolutionSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<AssetSeriesData>  $perAsset
     */
    public function __construct(
        public array $labels,
        public array $perAsset,
        public bool $hasMore = false,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'perAsset' => $this->perAsset,
            'hasMore' => $this->hasMore,
        ];
    }
}
