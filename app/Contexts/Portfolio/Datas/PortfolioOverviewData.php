<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class PortfolioOverviewData implements JsonSerializable
{
    /**
     * `$totalGain` est le gain latent des lignes encore détenues ; `$totalRealizedGain` celui déjà
     * encaissé sur les ventes, y compris sur des actifs entièrement soldés qui n'ont plus de ligne.
     *
     * @param  list<HoldingLineData>  $holdings
     */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public ?float $totalGainPct,
        public float $totalRealizedGain,
        public array $holdings,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalCost' => $this->totalCost,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'totalRealizedGain' => $this->totalRealizedGain,
            'holdings' => array_map(fn (HoldingLineData $h) => $h->jsonSerialize(), $this->holdings),
        ];
    }
}
