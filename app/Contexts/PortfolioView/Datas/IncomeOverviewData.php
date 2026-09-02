<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Le résumé de revenu d'une exposition, tel que la page liste l'affiche.
 */
readonly class IncomeOverviewData implements JsonSerializable
{
    /**
     * @param  float  $estimatedAnnual  revenu attendu sur les douze prochains mois
     * @param  array<string, float>  $bySource  montant perçu, indexé par origine de revenu
     */
    public function __construct(
        public float $totalReceived,
        public float $last12Months,
        public float $estimatedAnnual,
        public array $bySource,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'estimatedAnnual' => $this->estimatedAnnual,
            'bySource' => $this->bySource,
        ];
    }
}
