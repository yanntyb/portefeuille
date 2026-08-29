<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class PortfolioOverviewData implements JsonSerializable
{
    /** @param  list<HoldingLineData>  $holdings */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public ?float $totalGainPct,
        public array $holdings,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalCost' => $this->totalCost,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'holdings' => array_map(fn (HoldingLineData $h) => $h->jsonSerialize(), $this->holdings),
        ];
    }
}
