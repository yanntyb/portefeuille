<?php

namespace App\Contexts\Income\Datas;

use JsonSerializable;

readonly class IncomeSummaryData implements JsonSerializable
{
    /**
     * @param  array<string, float>  $bySource  montant perçu, indexé par valeur de `IncomeSource`
     */
    public function __construct(
        public float $totalReceived,
        public float $last12Months,
        public array $bySource,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'bySource' => $this->bySource,
        ];
    }
}
