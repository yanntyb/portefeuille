<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetInvestedSeriesData implements JsonSerializable
{
    /** @param list<float> $invested */
    public function __construct(
        public int $assetId,
        public string $name,
        public array $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'name' => $this->name,
            'invested' => $this->invested,
        ];
    }
}
