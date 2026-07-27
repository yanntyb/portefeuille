<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetSeriesData implements JsonSerializable
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
