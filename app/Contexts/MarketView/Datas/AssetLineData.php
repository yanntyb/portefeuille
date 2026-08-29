<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * La courbe d'un actif dans l'évolution d'une exposition : valeur et investi, point par point,
 * sur l'abscisse commune portée par `EvolutionData`.
 */
readonly class AssetLineData implements JsonSerializable
{
    /**
     * @param  list<float>  $value
     * @param  list<float>  $invested
     */
    public function __construct(
        public int $assetId,
        public string $name,
        public array $value,
        public array $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'name' => $this->name,
            'value' => $this->value,
            'invested' => $this->invested,
        ];
    }
}
