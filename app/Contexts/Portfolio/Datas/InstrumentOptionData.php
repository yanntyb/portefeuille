<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Un instrument tel qu'un formulaire le propose. `lastPrice` pré-remplit le prix unitaire — la
 * valeur la plus souvent juste pour un ordre saisi le jour même — et vaut `null` quand l'instrument
 * n'a jamais été coté, plutôt que zéro : un prix inconnu n'est pas un prix nul.
 */
readonly class InstrumentOptionData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public string $assetClass,
        public string $assetClassLabel,
        public ?float $lastPrice,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticker' => $this->ticker,
            'assetClass' => $this->assetClass,
            'assetClassLabel' => $this->assetClassLabel,
            'lastPrice' => $this->lastPrice,
        ];
    }
}
