<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetSeriesData implements JsonSerializable
{
    /**
     * `cash` porte les liquidités issues de cet actif — le produit de ses ventes, ses dividendes —
     * pour que la courbe d'une page d'exposition ne fasse plus de marche à la vente. Défaut vide
     * pour les séries antérieures aux liquidités, qui n'en portent pas.
     *
     * @param  list<float>  $value
     * @param  list<float>  $invested
     * @param  list<float>  $cash
     */
    public function __construct(
        public int $assetId,
        public string $name,
        public array $value,
        public array $invested,
        public array $cash = [],
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'name' => $this->name,
            'value' => $this->value,
            'invested' => $this->invested,
            'cash' => $this->cash,
        ];
    }
}
