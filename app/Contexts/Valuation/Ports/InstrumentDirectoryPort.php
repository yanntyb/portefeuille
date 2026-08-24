<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Market\Enums\AssetClass;

interface InstrumentDirectoryPort
{
    /**
     * Les actifs d'une ou plusieurs expositions. Ce qui permet de ne garder d'une série que les
     * actions, ou que les matières premières, sans que la valorisation ait à connaître le partage.
     *
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $classes): array;

    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
