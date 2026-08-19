<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Enums\InstrumentType;

interface DividendFeedPort
{
    /**
     * Dit si le flux couvre les détachements de ce type d'instrument.
     */
    public function supportsDividendFeed(InstrumentType $type): bool;

    /**
     * Récupère les détachements de plusieurs tickers d'un coup.
     *
     * Un ticker sans détachement sur sa fenêtre est absent du résultat : le flux ne distingue
     * pas un instrument capitalisant d'un ticker mort.
     *
     * @param  array<int, DividendRequestData>  $requests
     * @return array<string, array<int, DividendData>> détachements indexés par ticker
     *
     * @throws DividendFeedException quand la récupération échoue en totalité
     */
    public function fetchDividends(array $requests): array;
}
