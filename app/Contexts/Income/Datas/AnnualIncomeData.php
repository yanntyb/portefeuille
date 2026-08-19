<?php

namespace App\Contexts\Income\Datas;

use JsonSerializable;

readonly class AnnualIncomeData implements JsonSerializable
{
    /**
     * @param  array<string, float>  $bySource  montant perçu cette année-là, par source
     */
    public function __construct(
        public int $year,
        public float $total,
        public array $bySource,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'year' => $this->year,
            'total' => $this->total,
            'bySource' => $this->bySource,
        ];
    }
}
