<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Les repères d'analyse d'une position : sa référence de prix, sa tendance, son excès et son
 * risque. Nom distinct d'`AnalysisData`, qui porte déjà l'analyse d'une exposition entière.
 *
 * `price` est le dernier cours de la fenêtre lue, celui-là même auquel `pruGapPct` compare le prix
 * de revient : les deux doivent se lire l'un sous l'autre sans se contredire.
 *
 * Tout est nullable : un instrument sans cours dans la fenêtre garde son prix de revient et son
 * poids, et perd le reste. L'écran rend un tiret plutôt que de masquer la ligne.
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
        public ?float $price,
        public ?float $pru,
        public ?float $pruGapPct,
        public ?float $high52w,
        public ?float $high52wGapPct,
        public ?float $maxDrawdown,
        public ?float $portfolioWeightPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'price' => $this->price,
            'pru' => $this->pru,
            'pruGapPct' => $this->pruGapPct,
            'high52w' => $this->high52w,
            'high52wGapPct' => $this->high52wGapPct,
            'maxDrawdown' => $this->maxDrawdown,
            'portfolioWeightPct' => $this->portfolioWeightPct,
        ];
    }
}
