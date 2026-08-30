<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/** Ce qu'une exposition apprend d'elle-même : ce qu'elle concentre, et ce qui la fait avancer. */
readonly class PortfolioAnalysisData implements JsonSerializable
{
    /** @param  list<ContributionData>  $contributions */
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
                fn (ContributionData $line): array => $line->jsonSerialize(),
                $this->contributions,
            ),
        ];
    }
}
