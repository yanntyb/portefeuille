<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Un détachement perçu sur un actif : la quantité détenue ce jour-là, et ce qu'elle a rapporté.
 */
readonly class DividendLineData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $exDate,
        public float $quantity,
        public float $amountPerShare,
        public float $amount,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'exDate' => $this->exDate,
            'quantity' => $this->quantity,
            'amountPerShare' => $this->amountPerShare,
            'amount' => $this->amount,
        ];
    }
}
