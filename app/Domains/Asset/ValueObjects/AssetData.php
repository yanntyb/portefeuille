<?php

namespace App\Domains\Asset\ValueObjects;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Enums\Sector;

readonly class AssetData
{
    /**
     * @param  array<int, Sector>  $sectors
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
            sectors: array_map(fn (Sector|string $s) => $s instanceof Sector ? $s : Sector::tryFrom($s) ?? Sector::Other, $data['sectors'] ?? []),
        );
    }
}
