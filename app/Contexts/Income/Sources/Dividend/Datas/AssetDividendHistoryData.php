<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use JsonSerializable;

readonly class AssetDividendHistoryData implements JsonSerializable
{
    /**
     * @param  list<DividendReceiptData>  $receipts  du plus récent au plus ancien
     * @param  float|null  $yieldOnCost  perçu sur douze mois rapporté au coût de la position, en
     *                                   pourcentage ; nul sans position ou sans prix de revient
     */
    public function __construct(
        public array $receipts,
        public float $totalReceived,
        public float $last12Months,
        public ?float $yieldOnCost,
    ) {}

    public static function empty(): self
    {
        return new self([], 0.0, 0.0, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'receipts' => array_map(fn (DividendReceiptData $receipt): array => $receipt->jsonSerialize(), $this->receipts),
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'yieldOnCost' => $this->yieldOnCost,
        ];
    }
}
