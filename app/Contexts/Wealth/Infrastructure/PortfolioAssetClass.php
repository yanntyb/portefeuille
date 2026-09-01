<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\AssetClassPort;
use App\Contexts\Wealth\Services\SeriesAligner;

/**
 * Une classe d'actif tenue dans un portefeuille : ce que le portefeuille en vaut aujourd'hui, ce
 * que sa valorisation dit du passé, et ce qu'elle verse.
 *
 * Elle se déduit entièrement de son exposition : clé, libellé, page, teinte et origine de revenu
 * viennent tous de `AssetClass`. Une seule classe couvre ainsi toutes les expositions du
 * portefeuille ; `RealEstateClass` reste écrite à la main parce qu'elle n'en est pas une.
 */
class PortfolioAssetClass implements AssetClassPort
{
    public function __construct(
        private AssetClass $exposure,
        private GetPortfolioOverview $overview,
        private GetSectorBreakdown $sectors,
        private BuildEvolutionSeries $evolution,
        private GetIncomeSummary $income,
        private SeriesAligner $aligner,
        private PortfolioInvestedCapital $capital,
    ) {}

    public function key(): string
    {
        return $this->exposure->value;
    }

    public function label(): string
    {
        return $this->exposure->getLabel();
    }

    public function href(): string
    {
        return '/'.$this->exposure->slug();
    }

    public function color(): string
    {
        return $this->exposure->getColor();
    }

    public function incomeLabel(): ?string
    {
        return IncomeSource::forAssetClass($this->exposure)?->getLabel();
    }

    /**
     * Le réalisé additionne les plus-values de cession et le revenu déjà encaissé par l'exposition.
     *
     * `Portfolio` ne compte que les cessions — il ne connaît pas les revenus — et le dividende ne
     * se retrouvait donc nulle part : le gain latent le rate aussi, puisqu'il compare le dernier
     * cours au prix réellement payé, tous deux bruts. Seules les séries d'évolution portent des
     * cours ajustés, et elles ne servent pas ce compteur.
     *
     * L'investi n'est plus `totalCost` : celui-ci est le coût des titres détenus, donc il remonte
     * à chaque rachat financé par une vente et affichait « Investi 1 200, Gain 0 € » sur un
     * aller-retour qui n'avait sorti que 1 000 € de la poche. `InvestedCapital` porte la règle, et
     * `PortfolioInvestedCapital` lui donne la photo globale — la part d'une exposition dépend de
     * ce que les autres immobilisent, un arbitrage déplaçant le capital de l'une à l'autre.
     */
    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user, [$this->exposure]);

        return new ClassSnapshotData(
            value: $overview->totalValue,
            invested: $this->capital->forExposure($userId, $this->exposure),
            realized: round($overview->totalRealizedGain + $this->receivedIncomeFor($userId), 2),
        );
    }

    /** @return list<ClassSectorData> */
    public function sectorSlicesFor(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        /**
         * La part sans secteur prend le nom de son exposition plutôt que celui d'« Autre » : ni le
         * bitcoin ni l'or n'ont de secteur boursier, et « Crypto » dit ce qu'« Autre » cachait.
         */
        return array_map(
            fn (AllocationSliceData $slice): ClassSectorData => new ClassSectorData(
                label: $slice->label === Sector::Other->getLabel() ? $this->label() : $slice->label,
                value: $slice->value,
            ),
            ($this->sectors)($user, [$this->exposure]),
        );
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        /** Historique complet au pas hebdomadaire, comme le graphe du tableau de bord l'utilisait déjà. */
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week, [$this->exposure]);
        $length = count($series->labels);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->value, $series->perAsset),
                $length,
            ),
            invested: $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->invested, $series->perAsset),
                $length,
            ),
        );
    }

    public function monthlyIncomeFor(int $userId): float
    {
        $source = IncomeSource::forAssetClass($this->exposure);

        if ($source === null) {
            return 0.0;
        }

        /**
         * Filtré sur l'origine de la classe : `Income` agrège aussi les loyers, et l'immobilier
         * les compte déjà nets de son côté. Sans le filtre, ils seraient comptés deux fois.
         */
        return round(($this->income)($userId, $source)->last12Months / 12, 2);
    }

    /** Tout le revenu encaissé par l'exposition depuis toujours. Nul pour une classe muette. */
    private function receivedIncomeFor(int $userId): float
    {
        $source = IncomeSource::forAssetClass($this->exposure);

        return $source === null ? 0.0 : ($this->income)($userId, $source)->totalReceived;
    }
}
