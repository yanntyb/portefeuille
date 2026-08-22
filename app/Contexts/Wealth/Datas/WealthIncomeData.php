<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Ce que le patrimoine laisse chaque mois, et d'où ça vient. */
readonly class WealthIncomeData implements JsonSerializable
{
    /** @param  list<IncomeOriginData>  $origins */
    public function __construct(
        public float $monthlyTotal,
        public array $origins,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'monthlyTotal' => $this->monthlyTotal,
            'origins' => array_map(fn (IncomeOriginData $origin): array => $origin->jsonSerialize(), $this->origins),
        ];
    }
}
