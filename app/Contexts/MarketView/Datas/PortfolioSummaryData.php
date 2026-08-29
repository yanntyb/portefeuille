<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * L'aperçu chiffré d'une exposition : ses totaux, et les lignes qui les composent.
 */
readonly class PortfolioSummaryData implements JsonSerializable
{
    /**
     * @param  list<HoldingRowData>  $holdings
     */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public float $totalGainPct,
        public array $holdings,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalCost' => $this->totalCost,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'holdings' => array_map(
                fn (HoldingRowData $line): array => $line->jsonSerialize(),
                $this->holdings,
            ),
        ];
    }
}
