<?php

namespace App\Contexts\Valuation\Ports;

interface InstrumentDirectoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
