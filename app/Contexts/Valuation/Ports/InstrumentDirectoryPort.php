<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Market\Enums\InstrumentType;

interface InstrumentDirectoryPort
{
    /**
     * Les actifs d'une ou plusieurs classes. Ce qui permet de ne garder d'une série que les titres,
     * ou que la crypto, sans que la valorisation ait à connaître le partage.
     *
     * @param  list<InstrumentType>  $types
     * @return list<int>
     */
    public function idsOfTypes(array $types): array;

    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
