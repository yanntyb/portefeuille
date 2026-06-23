<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentData
{
    /**
     * @param  array<int, SectorAllocationData>  $sectors
     */
    public function __construct(
        public string $symbol,
        public string $name,
        public InstrumentType $type,
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
                fn (array|SectorAllocationData $s) => $s instanceof SectorAllocationData ? $s : SectorAllocationData::fromArray($s),
                $data['sectors'] ?? []
            ),
        );
    }
}
