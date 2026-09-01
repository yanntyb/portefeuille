<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\InstrumentType;

/**
 * Un résultat de recherche, plus pauvre qu'`InstrumentData` par nature : Yahoo peut rendre un type
 * que l'application ne sait pas traduire, et charger les secteurs de chaque ligne d'une liste
 * coûterait un aller-retour Python par ligne.
 *
 * `existingId` n'est posé que par la recherche locale : un instrument déjà en base s'affiche mais
 * ne se crée pas une seconde fois.
 */
readonly class InstrumentSearchResultData
{
    public function __construct(
        public string $symbol,
        public string $name,
        public ?string $exchange = null,
        public ?InstrumentType $type = null,
        public ?int $existingId = null,
    ) {}
}
