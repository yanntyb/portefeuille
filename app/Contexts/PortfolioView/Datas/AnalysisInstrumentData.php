<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Un instrument tel qu'il paraît dans la matrice de corrélations : son identité, et le libellé
 * court qui tient dans un en-tête de colonne. Le ticker sert quand il existe, le nom sinon.
 */
readonly class AnalysisInstrumentData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $label,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'label' => $this->label,
        ];
    }
}
