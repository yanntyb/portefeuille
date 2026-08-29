<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * Le revenu d'une année civile, tel que la page liste l'affiche.
 */
readonly class IncomeYearData implements JsonSerializable
{
    /**
     * @param  array<string, float>  $bySource  montant perçu cette année-là, par origine
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
