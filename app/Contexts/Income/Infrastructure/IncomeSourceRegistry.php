<?php

namespace App\Contexts\Income\Infrastructure;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;

class IncomeSourceRegistry
{
    /**
     * @param  iterable<IncomeSourcePort>  $sources
     */
    public function __construct(private iterable $sources) {}

    /**
     * Les revenus perçus par l'utilisateur. Sans `$only`, toutes origines confondues.
     *
     * @return list<IncomeReceiptData>
     */
    public function receiptsFor(int $userId, ?IncomeSource $only = null): array
    {
        $receipts = [];

        foreach ($this->sourcesFor($only) as $source) {
            foreach ($source->receiptsFor($userId) as $receipt) {
                $receipts[] = $receipt;
            }
        }

        return $receipts;
    }

    /** Revenu attendu sur les douze prochains mois. Sans `$only`, toutes origines confondues. */
    public function projectedAnnualFor(int $userId, ?IncomeSource $only = null): float
    {
        $projected = 0.0;

        foreach ($this->sourcesFor($only) as $source) {
            $projected += $source->projectedAnnualFor($userId);
        }

        return round($projected, 2);
    }

    /**
     * Le filtre porte sur l'origine déclarée par la source, jamais sur celle des reçus : une
     * source ne produit que des reçus de sa propre origine, l'interroger pour rien coûte ses
     * requêtes.
     *
     * @return iterable<IncomeSourcePort>
     */
    private function sourcesFor(?IncomeSource $only): iterable
    {
        foreach ($this->sources as $source) {
            if ($only === null || $source->source() === $only) {
                yield $source;
            }
        }
    }
}
