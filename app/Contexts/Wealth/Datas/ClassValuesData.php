<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Une classe d'actif reportée sur la grille commune : le graphe en fait une bande. */
readonly class ClassValuesData implements JsonSerializable
{
    /** @param  list<float>  $values */
    public function __construct(
        public string $key,
        public string $label,
        public string $color,
        public array $values,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'color' => $this->color,
            'values' => $this->values,
        ];
    }
}
