<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

readonly class TransactionLineData implements JsonSerializable
{
    public function __construct(
        public string $date,
        public bool $isSell,
        public string $typeLabel,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public float $total,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'isSell' => $this->isSell,
            'typeLabel' => $this->typeLabel,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'fees' => $this->fees,
            'total' => $this->total,
        ];
    }
}
