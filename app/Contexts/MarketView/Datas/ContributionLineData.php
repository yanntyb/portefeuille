<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\ContributionData` : mêmes clés, même ordre. */
readonly class ContributionLineData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $assetName,
        public ?float $contribution,
        public ?float $weight,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'contribution' => $this->contribution,
            'weight' => $this->weight,
        ];
    }
}
