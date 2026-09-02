<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\HoldingSnapshotData;
use App\Contexts\PortfolioView\Datas\HoldingTrendData;
use App\Contexts\PortfolioView\Ports\HoldingsPort;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
use App\Contexts\PortfolioView\Services\SparklineReducer;
use Illuminate\Support\Carbon;

class GetHoldingTrends
{
    /** Enough points for a readable sparkline, few enough to keep the payload small. */
    private const MAX_POINTS = 24;

    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
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
        $assetIds = array_map(
            fn (HoldingSnapshotData $snapshot): int => $snapshot->assetId,
            $this->holdings->holdingsFor($userId),
        );

        if ($classes !== null) {
            $kept = array_flip($this->market->idsOfClasses($assetIds, $classes));
            $assetIds = array_values(array_filter($assetIds, fn (int $id): bool => isset($kept[$id])));
        }

        $closes = $this->market->closeSeriesSince($assetIds, Carbon::createFromTimestamp(0));

        return array_map(
            fn (int $assetId): HoldingTrendData => $this->toTrend($assetId, $closes[$assetId] ?? []),
            $assetIds,
        );
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
