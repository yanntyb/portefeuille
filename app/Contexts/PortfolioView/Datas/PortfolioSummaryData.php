<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * L'aperçu chiffré d'une exposition : ses totaux, et les lignes qui les composent.
 */
readonly class PortfolioSummaryData implements JsonSerializable
{
    /**
     * `$cash` jumelle `Portfolio\PortfolioOverviewData::$cash` : le solde d'espèces de
     * l'utilisateur, toutes enveloppes confondues, jamais ventilé par exposition — la même valeur
     * revient donc quelle que soit l'exposition demandée. C'est le repère « à replacer » de la page.
     *
     * @param  list<HoldingRowData>  $holdings
     */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public ?float $totalGainPct,
        public float $totalRealizedGain,
        public float $cash,
        public array $holdings,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, 0.0, 0.0, []);
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
            'cash' => $this->cash,
            'holdings' => array_map(
                fn (HoldingRowData $line): array => $line->jsonSerialize(),
                $this->holdings,
            ),
        ];
    }
}
