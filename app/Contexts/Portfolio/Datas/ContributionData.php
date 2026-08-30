<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/** Ce qu'une position apporte au rendement du portefeuille, et la place qu'elle y occupe. */
readonly class ContributionData implements JsonSerializable
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
