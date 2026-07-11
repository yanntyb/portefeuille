<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class InvestedByAssetSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<AssetInvestedSeriesData>  $series
     */
    public function __construct(
        public array $labels,
        public array $series,
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
            'series' => $this->series,
        ];
    }
}
