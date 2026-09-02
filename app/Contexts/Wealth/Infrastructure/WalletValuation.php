<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Valuation\Actions\BuildExposureSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\ValuationPort;

/**
 * L'action est injectée, jamais construite : sa série est retenue sous un nom de cache qui porte
 * l'enveloppe, et le contrôleur de la page la redemande à l'identique quand une prop différée
 * revient.
 *
 * Aucun calcul ici, seulement une traduction : `ValuationSeriesData::$valuations` devient
 * `ClassSeriesData::$value`, le nom que le patrimoine emploie pour ses séries.
 */
class WalletValuation implements ValuationPort
{
    public function __construct(private BuildExposureSeries $series) {}

    public function seriesForWallet(int $userId, int $walletId): ClassSeriesData
    {
        $series = ($this->series)($userId, null, $walletId);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $series->valuations,
            invested: $series->invested,
        );
    }
}
