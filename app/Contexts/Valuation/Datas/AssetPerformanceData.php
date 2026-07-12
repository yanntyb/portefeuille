<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetPerformanceData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $pct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'pct' => $this->pct,
        ];
    }
}
