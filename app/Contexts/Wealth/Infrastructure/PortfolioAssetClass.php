<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\AssetClassPort;

/**
 * Une classe d'actif tenue dans un portefeuille : ce que le portefeuille en vaut aujourd'hui, ce
 * que sa valorisation dit du passé, et ce qu'elle verse.
 *
 * Les classes concrètes ne diffèrent que par leur identité et par les types d'instrument qu'elles
 * gardent : recopier ces trois lectures pour chacune les ferait diverger à la première correction.
 */
abstract class PortfolioAssetClass implements AssetClassPort
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private BuildEvolutionSeries $evolution,
        private GetIncomeSummary $income,
    ) {}

    /**
     * Les expositions que la classe garde, ou `null` pour tout le portefeuille.
     *
     * @return ?list<AssetClass>
     */
    abstract protected function classes(): ?array;

    /** L'origine de revenu à interroger, ou `null` quand la classe ne verse rien. */
    protected function incomeSource(): ?IncomeSource
    {
        return null;
    }

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user, $this->classes());

        return new ClassSnapshotData(value: $overview->totalValue, invested: $overview->totalCost);
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        /** Historique complet au pas hebdomadaire, comme le graphe du tableau de bord l'utilisait déjà. */
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week, $this->classes());

        return new ClassSeriesData(
            labels: $series->labels,
            value: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->value, count($series->labels)),
            invested: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->invested, count($series->labels)),
        );
    }

    public function monthlyIncomeFor(int $userId): float
    {
        $source = $this->incomeSource();

        if ($source === null) {
            return 0.0;
        }

        /**
         * Filtré sur l'origine de la classe : `Income` agrège aussi les loyers, et l'immobilier
         * les compte déjà nets de son côté. Sans le filtre, ils seraient comptés deux fois.
         */
        return round(($this->income)($userId, $source)->last12Months / 12, 2);
    }

    /**
     * @param  list<AssetSeriesData>  $perAsset
     * @param  callable(AssetSeriesData): list<float>  $pick
     * @return list<float>
     */
    private function sum(array $perAsset, callable $pick, int $length): array
    {
        $totals = array_fill(0, $length, 0.0);

        foreach ($perAsset as $asset) {
            foreach ($pick($asset) as $index => $amount) {
                $totals[$index] = ($totals[$index] ?? 0.0) + $amount;
            }
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }
}
