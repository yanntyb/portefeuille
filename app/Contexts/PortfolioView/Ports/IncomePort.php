<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\DividendHistoryData;
use App\Contexts\PortfolioView\Datas\IncomeOverviewData;
use App\Contexts\PortfolioView\Datas\IncomeYearData;

/**
 * Le revenu d'une exposition, vu de la page liste. L'origine du revenu — dividende, loyer — reste
 * derrière ce port : la page décide d'afficher sa section sur `supportsExposure()`, jamais sur un
 * enum du contexte voisin.
 */
interface IncomePort
{
    /** Vrai quand l'exposition rapporte un revenu, et donc que la section a lieu d'être. */
    public function supportsExposure(AssetClass $exposure): bool;

    public function summaryFor(int $userId, AssetClass $exposure): IncomeOverviewData;

    /** @return list<IncomeYearData> */
    public function annualFor(int $userId, AssetClass $exposure): array;

    /** Les détachements d'un actif, tels que sa fiche les montre. */
    public function assetHistoryFor(int $userId, int $assetId): DividendHistoryData;
}
