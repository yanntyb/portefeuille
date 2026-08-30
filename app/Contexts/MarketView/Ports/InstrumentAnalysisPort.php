<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\InstrumentAnalysisData;

/**
 * Les repères d'analyse d'une position. Un port à part et non une méthode de plus sur
 * `MarketDataPort` : cette lecture croise le marché, le portefeuille et la valorisation, elle
 * n'est pas une lecture de marché.
 */
interface InstrumentAnalysisPort
{
    /** Nul quand l'utilisateur ne détient pas l'actif : sans position, il n'y a rien à analyser. */
    public function forAsset(int $userId, int $assetId): ?InstrumentAnalysisData;
}
