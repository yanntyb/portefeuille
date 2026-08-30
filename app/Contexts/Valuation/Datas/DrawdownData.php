<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

/**
 * La pire perte vécue depuis un plus-haut, et celle en cours. Les deux profondeurs sont des
 * pourcentages positifs : une chute de 1000 à 750 vaut 25.
 *
 * Les dates sont nulles quand la série ne baisse jamais — il n'y a alors ni plus-haut suivi d'un
 * creux, ni creux à dater.
 */
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
