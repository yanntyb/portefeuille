<?php

namespace App\Contexts\Income\Infrastructure;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Ports\IncomeSourcePort;

class IncomeSourceRegistry
{
    /**
     * @param  iterable<IncomeSourcePort>  $sources
     */
    public function __construct(private iterable $sources) {}

    /**
     * Tous les revenus perçus par l'utilisateur, toutes origines confondues.
     *
     * @return list<IncomeReceiptData>
     */
    public function receiptsFor(int $userId): array
    {
        $receipts = [];

        foreach ($this->sources as $source) {
            foreach ($source->receiptsFor($userId) as $receipt) {
                $receipts[] = $receipt;
            }
        }

        return $receipts;
    }
}
