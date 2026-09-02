<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Les détachements d'un actif, du plus récent au plus ancien, et ce qu'ils pèsent.
 */
readonly class DividendHistoryData implements JsonSerializable
{
    /**
     * @param  list<DividendLineData>  $receipts
     * @param  float  $estimatedAnnual  attendu sur les douze prochains mois, extrapolé du passé récent
     * @param  ?float  $yieldOnCost  perçu sur douze mois rapporté au coût de la position, en pourcentage
     */
    public function __construct(
        public array $receipts,
        public float $totalReceived,
        public float $last12Months,
        public float $estimatedAnnual,
        public ?float $yieldOnCost,
    ) {}

    public static function empty(): self
    {
        return new self([], 0.0, 0.0, 0.0, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'receipts' => array_map(
                fn (DividendLineData $receipt): array => $receipt->jsonSerialize(),
                $this->receipts,
            ),
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'estimatedAnnual' => $this->estimatedAnnual,
            'yieldOnCost' => $this->yieldOnCost,
        ];
    }
}
