<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Services\HoldingValuator;

class GetPortfolioOverview
{
    /**
     * Les lignes de chaque utilisateur, pour la durée de la requête. Cinq classes d'actif
     * lisaient autrefois cinq fois les mêmes positions et les mêmes prix ; le portefeuille tient
     * en quelques dizaines de lignes, le découpage se fait donc en mémoire.
     *
     * @var array<int, list<HoldingLineData>>
     */
    private array $linesByUser = [];

    public function __construct(
        private PriceRepositoryContract $prices,
        private HoldingValuator $valuator,
    ) {}

    /**
     * Sans `$classes`, tout le portefeuille. Avec, une ou plusieurs expositions : chacune a sa
     * page et sa ligne au patrimoine, et le partage se lit dans `AssetClass`, nulle part ailleurs.
     *
     * @param  ?list<AssetClass>  $classes
     */
    public function __invoke(User $user, ?array $classes = null): PortfolioOverviewData
    {
        $lines = $this->linesByUser[$user->id] ??= $this->readLines($user);

        if ($classes !== null) {
            $kept = array_flip(array_map(fn (AssetClass $class): string => $class->value, $classes));
            $lines = array_values(array_filter(
                $lines,
                fn (HoldingLineData $line): bool => isset($kept[$line->assetClass->value]),
            ));
        }

        return $this->summarize($lines);
    }

    /**
     * Toutes les positions de l'utilisateur, valorisées, sans filtrage par exposition : celui-ci
     * se fait en mémoire dans `__invoke()`, sur le résultat mémoïsé de cette méthode.
     *
     * @return list<HoldingLineData>
     */
    private function readLines(User $user): array
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->get();

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        $lines = [];

        foreach ($holdings as $holding) {
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;
            $lastPrice = $lastPrices[(int) $holding->asset_id] ?? null;

            $valued = $this->valuator->value($quantity, $avgCost, $lastPrice);

            $lines[] = new HoldingLineData(
                assetId: (int) $holding->asset_id,
                assetName: $holding->asset->name,
                ticker: $holding->asset->ticker,
                type: $holding->asset->type,
                assetClass: $holding->asset->asset_class,
                quantity: $quantity,
                avgCost: $avgCost,
                lastPrice: $lastPrice,
                marketValue: $valued['marketValue'],
                gain: $valued['gain'],
                gainPct: $valued['gainPct'],
            );
        }

        return $lines;
    }

    /**
     * Le seul totalisage : le total du portefeuille entier et celui d'une exposition passent
     * tous deux par ici, sur les lignes déjà retenues par `__invoke()`.
     *
     * @param  list<HoldingLineData>  $lines
     */
    private function summarize(array $lines): PortfolioOverviewData
    {
        $totals = $this->valuator->totals($lines);

        return new PortfolioOverviewData(
            totalValue: $totals['totalValue'],
            totalCost: $totals['totalCost'],
            totalGain: $totals['totalGain'],
            totalGainPct: $totals['totalGainPct'],
            holdings: $lines,
        );
    }
}
