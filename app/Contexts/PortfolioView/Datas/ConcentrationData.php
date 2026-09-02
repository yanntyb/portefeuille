<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\ConcentrationData` : mêmes clés, même ordre. */
readonly class ConcentrationData implements JsonSerializable
{
    public function __construct(
        public ?float $top1,
        public ?float $top3,
        public ?float $top5,
        public ?float $hhi,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'top1' => $this->top1,
            'top3' => $this->top3,
            'top5' => $this->top5,
            'hhi' => $this->hhi,
        ];
    }
}
