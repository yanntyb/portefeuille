<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\HoldingLineData;

/**
 * L'action est injectée, jamais résolue ni construite ici : elle est liée en `scoped` et mémoïse
 * ses lignes par utilisateur, si bien qu'une seule lecture du portefeuille sert les quatre
 * expositions d'une requête. La reconstruire la relirait une fois par exposition.
 */
class PortfolioTotals implements PortfolioOverviewPort
{
    public function __construct(private GetPortfolioOverview $overview) {}

    public function overviewFor(int $userId, AssetClass $exposure): PortfolioSummaryData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return PortfolioSummaryData::empty();
        }

        $overview = ($this->overview)($user, [$exposure]);

        return new PortfolioSummaryData(
            totalValue: $overview->totalValue,
            totalCost: $overview->totalCost,
            totalGain: $overview->totalGain,
            totalGainPct: $overview->totalGainPct,
            holdings: array_map(
                fn (HoldingLineData $line): HoldingRowData => new HoldingRowData(
                    assetId: $line->assetId,
                    assetName: $line->assetName,
                    ticker: $line->ticker,
                    type: $line->type,
                    assetClass: $line->assetClass,
                    quantity: $line->quantity,
                    avgCost: $line->avgCost,
                    lastPrice: $line->lastPrice,
                    marketValue: $line->marketValue,
                    gain: $line->gain,
                    gainPct: $line->gainPct,
                ),
                $overview->holdings,
            ),
        );
    }
}
