<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/** Jumelle de `Valuation\Datas\DrawdownData` : mêmes clés, même ordre. */
readonly class DrawdownData implements JsonSerializable
{
    public function __construct(
        public ?float $maxDepth,
        public ?string $peakLabel,
        public ?string $troughLabel,
        public ?float $currentDepth,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'maxDepth' => $this->maxDepth,
            'peakLabel' => $this->peakLabel,
            'troughLabel' => $this->troughLabel,
            'currentDepth' => $this->currentDepth,
        ];
    }
}
