<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\PortfolioView\Datas\HoldingTrendData;
use App\Contexts\PortfolioView\Services\SparklineReducer;
use Illuminate\Support\Carbon;

class GetHoldingTrends
{
    /** Enough points for a readable sparkline, few enough to keep the payload small. */
    private const MAX_POINTS = 24;

    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private SparklineReducer $sparkline,
    ) {}

    /**
     * Les tendances des instruments détenus. Sans `$classes`, tout le portefeuille ; avec, une
     * exposition — sinon l'instantané hors-ligne porterait le portefeuille entier une fois par
     * classe, pour un rendu qui n'en montre qu'une part.
     *
     * L'historique est pris en entier : la sparkline n'a pas de sélecteur de plage, et le
     * sous-échantillonnage lui donne de toute façon le même nombre de points.
     *
     * @param  ?list<AssetClass>  $classes
     * @return list<HoldingTrendData>
     */
    public function __invoke(int $userId, ?array $classes = null): array
    {
        $assetIds = array_values(array_map(
            fn (PositionLineData $position): int => $position->assetId,
            ($this->positions)($userId),
        ));

        if ($classes !== null) {
            $kept = array_flip($this->idsOfClasses($assetIds, $classes));
            $assetIds = array_values(array_filter($assetIds, fn (int $id): bool => isset($kept[$id])));
        }

        $closes = $this->prices->closesForAssetsSince($assetIds, Carbon::createFromTimestamp(0));

        return array_map(
            fn (int $assetId): HoldingTrendData => $this->toTrend($assetId, $closes[$assetId] ?? []),
            $assetIds,
        );
    }

    /**
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    private function idsOfClasses(array $assetIds, array $classes): array
    {
        if ($assetIds === [] || $classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @param list<float> $close */
    private function toTrend(int $assetId, array $close): HoldingTrendData
    {
        return new HoldingTrendData(
            assetId: $assetId,
            changePct: $this->sparkline->changePct($close),
            points: $this->sparkline->downsample($close, self::MAX_POINTS),
        );
    }
}
