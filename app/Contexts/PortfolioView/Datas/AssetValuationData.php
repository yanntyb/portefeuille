<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * La valorisation d'un actif seul, point par point : ce qu'il vaut, ce qu'il a coûté, et son cours.
 */
readonly class AssetValuationData implements JsonSerializable
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
