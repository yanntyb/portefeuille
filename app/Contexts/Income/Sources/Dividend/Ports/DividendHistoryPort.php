<?php

namespace App\Contexts\Income\Sources\Dividend\Ports;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;

/**
 * Ce que la source dividende attend du marché. `namesFor()` y vit aussi : le nom de l'instrument
 * n'est lu que pour étiqueter un reçu, et lui donner son propre port n'ajouterait qu'un fichier.
 */
interface DividendHistoryPort
{
    /**
     * @param  array<int>  $assetIds
     * @return list<DividendRecordData>
     */
    public function forAssets(array $assetIds): array;

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
