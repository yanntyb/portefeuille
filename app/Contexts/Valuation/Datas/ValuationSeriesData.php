<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class ValuationSeriesData implements JsonSerializable
{
    /**
     * `cash` a un défaut vide : les séries construites à la main pour les tests de fenêtrage et de
     * performance, antérieures aux liquidités, n'en portent pas et n'en ont pas besoin.
     *
     * @param  list<string>  $labels
     * @param  list<float>  $valuations
     * @param  list<float>  $invested
     * @param  list<float>  $prices
     * @param  list<float>  $cash
     */
    public function __construct(
        public array $labels,
        public array $valuations,
        public array $invested,
        public array $prices,
        public array $cash = [],
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'valuations' => $this->valuations,
            'invested' => $this->invested,
            'prices' => $this->prices,
            'cash' => $this->cash,
        ];
    }
}
