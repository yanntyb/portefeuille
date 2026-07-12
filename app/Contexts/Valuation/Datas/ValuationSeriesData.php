<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class ValuationSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $valuations
     * @param  list<float>  $invested
     * @param  list<float>  $prices
     */
    public function __construct(
        public array $labels,
        public array $valuations,
        public array $invested,
        public array $prices,
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
            'valuations' => $this->valuations,
            'invested' => $this->invested,
            'prices' => $this->prices,
        ];
    }
}
