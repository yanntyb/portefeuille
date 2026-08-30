<?php

namespace App\Contexts\Portfolio\Datas;

/**
 * Une position par actif, enveloppes confondues — par opposition à `HoldingLineData`, qui compte
 * une ligne par enveloppe. Les deux notions coexistent volontairement : une page liste montre les
 * lignes, une fiche montre la position.
 */
readonly class PositionLineData
{
    public function __construct(
        public int $assetId,
        public float $quantity,
        public ?float $avgCost,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
        /** Gain déjà encaissé sur cet actif, toutes enveloppes confondues, nul faute de vente. */
        public float $realizedGain,
    ) {}
}
