<?php

namespace App\Contexts\Market\Datas;

use JsonSerializable;

/**
 * Un panier d'instruments ramené à une seule série, base 100 à sa première séance. `labels` et
 * `values` ont la même longueur et le même ordre : c'est ce que `Valuation\Services\Drawdown`
 * attend.
 */
readonly class BasketIndexData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     */
    public function __construct(
        public array $labels,
        public array $values,
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
            'values' => $this->values,
        ];
    }
}
