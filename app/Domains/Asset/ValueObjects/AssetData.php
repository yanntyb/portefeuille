<?php

namespace App\Domains\Asset\ValueObjects;

use App\Domains\Asset\Enums\AssetType;

readonly class AssetData
{
    /**
     * @param  array<int, SectorAllocation>  $sectors
     */
    public function __construct(
        public string $symbol,
        public string $name,
        public AssetType $type,
        public ?string $exchange = null,
        public ?string $currency = null,
        public array $sectors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            symbol: $data['symbol'],
            name: $data['name'],
            type: $data['type'],
            exchange: $data['exchange'] ?? null,
            currency: $data['currency'] ?? null,
            sectors: array_map(
                fn (array|SectorAllocation $s) => $s instanceof SectorAllocation ? $s : SectorAllocation::fromArray($s),
                $data['sectors'] ?? []
            ),
        );
    }
}
