<?php

namespace App\Contexts\Income\Datas;

use App\Contexts\Income\Enums\IncomeSource;
use Illuminate\Support\Carbon;

/**
 * Un revenu perçu, quelle que soit son origine. `assetId` reste nul pour un revenu qui ne porte
 * sur aucun instrument — un loyer, par exemple —, `label` portant alors seul son identité.
 */
readonly class IncomeReceiptData
{
    public function __construct(
        public IncomeSource $source,
        public Carbon $date,
        public float $amount,
        public ?int $assetId = null,
        public ?string $label = null,
    ) {}
}
