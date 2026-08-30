<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Datas\AnalysisData;
use App\Contexts\MarketView\Datas\ConcentrationData;
use App\Contexts\MarketView\Datas\ContributionLineData;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\MarketView\Datas\PortfolioSummaryData;
use App\Contexts\MarketView\Datas\PositionData;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\Portfolio\Actions\GetPortfolioAnalysis;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\ContributionData;
use App\Contexts\Portfolio\Datas\HoldingLineData;

/**
 * Les trois actions sont injectées, jamais résolues ni construites ici : elles sont liées en
 * `scoped` et mémoïsent leurs lignes par utilisateur, si bien qu'une seule lecture du portefeuille
 * sert les quatre expositions d'une requête. Les reconstruire les relirait une fois par exposition.
 */
class PortfolioTotals implements PortfolioOverviewPort
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetPortfolioPositions $positions,
        private GetPortfolioAnalysis $analysis,
    ) {}

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

    public function positionFor(int $userId, int $assetId): ?PositionData
    {
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new PositionData(
            quantity: $position->quantity,
            avgCost: $position->avgCost,
            marketValue: $position->marketValue,
            gain: $position->gain,
            gainPct: $position->gainPct,
        );
    }

    public function analysisFor(int $userId, AssetClass $exposure): AnalysisData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return AnalysisData::empty();
        }

        $analysis = ($this->analysis)($user, [$exposure]);

        return new AnalysisData(
            concentration: new ConcentrationData(
                top1: $analysis->concentration->top1,
                top3: $analysis->concentration->top3,
                top5: $analysis->concentration->top5,
                hhi: $analysis->concentration->hhi,
            ),
            contributions: array_map(
                fn (ContributionData $line): ContributionLineData => new ContributionLineData(
                    assetId: $line->assetId,
                    assetName: $line->assetName,
                    contribution: $line->contribution,
                    weight: $line->weight,
                ),
                $analysis->contributions,
            ),
        );
    }
}
