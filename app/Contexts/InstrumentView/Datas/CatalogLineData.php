<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

readonly class CatalogLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public InstrumentType $type,
        public ?float $lastPrice,
        public bool $held,
        public ?float $quantity,
        public ?float $marketValue,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticker' => $this->ticker,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'lastPrice' => $this->lastPrice,
            'held' => $this->held,
            'quantity' => $this->quantity,
            'marketValue' => $this->marketValue,
        ];
    }
}
