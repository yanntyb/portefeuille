<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * L'analyse d'une exposition entière : ce que la poche a encaissé, et à quel point ses lignes
 * bougent ensemble.
 *
 * `maxDrawdown` est un pourcentage positif — une chute de 120 à 90 vaut 25 — quand
 * `high52wGapPct` est négatif ou nul : c'est la convention déjà tenue par `InstrumentAnalysisData`,
 * et les deux se lisent l'une sous l'autre.
 *
 * `instruments` donne l'ordre des lignes comme celui des colonnes de `correlations` : la case
 * `correlations[i][j]` corrèle `instruments[i]` à `instruments[j]`.
 *
 * L'ordre des clés de `jsonSerialize()` est un contrat : le hash de l'instantané hors-ligne en
 * dépend, et un ordre différent le ferait retélécharger à tous les clients.
 */
readonly class ClassAnalysisData implements JsonSerializable
{
    /**
     * @param  list<AnalysisInstrumentData>  $instruments
     * @param  list<list<?float>>  $correlations
     */
    public function __construct(
        public ?float $maxDrawdown,
        public ?float $high52wGapPct,
        public array $instruments,
        public array $correlations,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'maxDrawdown' => $this->maxDrawdown,
            'high52wGapPct' => $this->high52wGapPct,
            'instruments' => array_map(
                fn (AnalysisInstrumentData $line): array => $line->jsonSerialize(),
                $this->instruments,
            ),
            'correlations' => $this->correlations,
        ];
    }
}
