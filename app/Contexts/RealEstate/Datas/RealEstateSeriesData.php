<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Valeur nette et cash investi du parc immobilier, semaine par semaine. */
readonly class RealEstateSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $netWorth
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $netWorth,
        public array $invested,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'netWorth' => $this->netWorth,
            'invested' => $this->invested,
        ];
    }
}
