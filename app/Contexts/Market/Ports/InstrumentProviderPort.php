<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\InstrumentSearchResultData;
use App\Contexts\Market\Enums\InstrumentType;

interface InstrumentProviderPort
{
    /**
     * Fetch asset metadata from external source by ticker symbol
     */
    public function findBySymbol(string $symbol, InstrumentType $type): ?InstrumentData;

    /**
     * Cherche des instruments par nom ou par symbole, sans type imposé.
     *
     * Distincte de `findBySymbol()`, qui résout un symbole déjà connu : ici le type est
     * précisément ce qu'on cherche à apprendre, et les N résultats sont le matériau d'un choix.
     *
     * @return list<InstrumentSearchResultData>
     */
    public function searchInstruments(string $query): array;

    /**
     * Check if the provider exposes metadata for this instrument type.
     */
    public function supportsInstruments(InstrumentType $type): bool;
}
