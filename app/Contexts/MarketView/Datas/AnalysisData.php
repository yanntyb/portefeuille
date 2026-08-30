<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\PortfolioAnalysisData` : mêmes clés, même ordre. */
readonly class AnalysisData implements JsonSerializable
{
    /** @param  list<ContributionLineData>  $contributions */
    public function __construct(
        public ConcentrationData $concentration,
        public array $contributions,
    ) {}

    public static function empty(): self
    {
        return new self(ConcentrationData::empty(), []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'concentration' => $this->concentration->jsonSerialize(),
            'contributions' => array_map(
                fn (ContributionLineData $line): array => $line->jsonSerialize(),
                $this->contributions,
            ),
        ];
    }
}
