<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioAnalysisData;
use App\Contexts\Portfolio\Services\Concentration;
use App\Contexts\Portfolio\Services\HoldingValuator;
use App\Contexts\Portfolio\Services\PerformanceContribution;
use App\Contexts\Portfolio\Services\PositionAggregator;

/**
 * Les analyses d'une exposition, mesurées sur ses positions et non sur ses lignes : un titre tenu
 * dans deux enveloppes est une seule exposition au marché, et le compter deux fois sous-estimerait
 * précisément ce que la concentration sert à détecter.
 *
 * Repart de `GetPortfolioOverview`, mémoïsée par utilisateur et déjà filtrée par exposition, plutôt
 * que de `GetPortfolioPositions`, qui ne filtre ni ne nomme.
 */
class GetPortfolioAnalysis
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private PositionAggregator $aggregate,
        private HoldingValuator $valuator,
        private Concentration $concentration,
        private PerformanceContribution $contribution,
    ) {}

    /** @param  ?list<AssetClass>  $classes */
    public function __invoke(User $user, ?array $classes = null): PortfolioAnalysisData
    {
        $overview = ($this->overview)($user, $classes);

        if ($overview->holdings === []) {
            return PortfolioAnalysisData::empty();
        }

        $positions = $this->positionsOf($overview->holdings);

        return new PortfolioAnalysisData(
            concentration: $this->concentration->of(array_column($positions, 'marketValue')),
            contributions: $this->contribution->of($positions, $overview->totalValue),
        );
    }

    /**
     * Les lignes regroupées par actif : quantités sommées, prix de revient moyenné, valeur et gain
     * recalculés sur la position entière.
     *
     * @param  list<HoldingLineData>  $lines
     * @return list<array{assetId: int, assetName: string, gain: ?float, marketValue: ?float}>
     */
    private function positionsOf(array $lines): array
    {
        $byAsset = [];

        foreach ($lines as $line) {
            $byAsset[$line->assetId][] = $line;
        }

        $positions = [];

        foreach ($byAsset as $assetId => $assetLines) {
            $aggregated = ($this->aggregate)(array_map(fn (HoldingLineData $line): array => [
                'quantity' => $line->quantity,
                'avgCost' => $line->avgCost,
            ], $assetLines));

            $valued = $this->valuator->value(
                $aggregated['quantity'],
                $aggregated['avgCost'],
                $assetLines[0]->lastPrice,
            );

            $positions[] = [
                'assetId' => (int) $assetId,
                'assetName' => $assetLines[0]->assetName,
                'gain' => $valued['gain'],
                'marketValue' => $valued['marketValue'],
            ];
        }

        return $positions;
    }
}
