<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * Les repères d'analyse d'une position : sa référence de prix, sa tendance, son excès et son
 * risque. Nom distinct d'`AnalysisData`, qui porte déjà l'analyse d'une exposition entière.
 *
 * Tout est nullable : un instrument coté depuis six mois n'a pas de moyenne à deux cents séances,
 * une série plate pas d'amplitude utile. L'écran rend un tiret plutôt que de masquer la ligne.
 *
 * `maxDrawdown` est un pourcentage positif, comme le `maxDepth` de `Valuation\Datas\DrawdownData`
 * dont il vient : une chute de 1000 à 750 vaut 25. Le signe est posé à l'affichage.
 *
 * L'ordre des clés de `jsonSerialize()` est un contrat : le hash de l'instantané hors-ligne en
 * dépend, et un ordre différent le ferait retélécharger à tous les clients.
 */
readonly class InstrumentAnalysisData implements JsonSerializable
{
    public function __construct(
        public ?float $pru,
        public ?float $pruGapPct,
        public ?float $high52w,
        public ?float $high52wGapPct,
        public ?float $atr,
        public ?float $atrPct,
        public ?float $maxDrawdown,
        public ?float $portfolioWeightPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'pru' => $this->pru,
            'pruGapPct' => $this->pruGapPct,
            'high52w' => $this->high52w,
            'high52wGapPct' => $this->high52wGapPct,
            'atr' => $this->atr,
            'atrPct' => $this->atrPct,
            'maxDrawdown' => $this->maxDrawdown,
            'portfolioWeightPct' => $this->portfolioWeightPct,
        ];
    }
}
