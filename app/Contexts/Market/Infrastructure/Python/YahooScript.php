<?php

namespace App\Contexts\Market\Infrastructure\Python;

enum YahooScript: string
{
    case Prices = 'fetch_prices.py';
    case Search = 'search_ticker.py';
    case Sectors = 'fetch_sectors.py';

    /**
     * Chemin absolu du script, co-localisé dans ce dossier.
     */
    public function path(): string
    {
        return __DIR__.'/'.$this->value;
    }
}
