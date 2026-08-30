<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Ce qu'un portefeuille concentre. Les quatre indicateurs sont nuls, et non zéro, quand aucune
 * position n'a de valeur connue : un portefeuille sans exposition n'a pas une concentration de
 * zéro, il n'en a pas.
 */
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
