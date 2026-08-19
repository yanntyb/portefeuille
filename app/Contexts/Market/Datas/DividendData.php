<?php

namespace App\Contexts\Market\Datas;

readonly class DividendData
{
    public function __construct(
        public string $exDate,
        public float $amountPerShare,
    ) {}

    /**
     * @param  array{ex_date: string, amount_per_share: float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            exDate: $data['ex_date'],
            amountPerShare: (float) $data['amount_per_share'],
        );
    }
}
