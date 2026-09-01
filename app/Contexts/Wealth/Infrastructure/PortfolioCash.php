<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Services\CashLedger;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\CashPort;
use App\Contexts\Wealth\Services\SeriesAligner;

/**
 * Traduit les mouvements d'espèces de `Portfolio` en instantané et série pour `Wealth`, qui n'en
 * connaît que `CashPort`.
 */
class PortfolioCash implements CashPort
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetCashMovements $movements,
        private CashLedger $ledger,
        private BuildEvolutionSeries $evolution,
        private SeriesAligner $aligner,
    ) {}

    /**
     * `value` est le solde toutes enveloppes confondues. `invested` suit l'argent : tant qu'un
     * apport est en titres, il est imputé à l'exposition (`totalCost`) ; dès que les titres sont
     * vendus, le capital revient aux liquidités. L'étiquette d'origine du FIFO
     * (`CashLedger::compositionAt()`) ne convient pas ici — elle dit d'où vient un euro, pas s'il
     * est capital ou gain, et le produit d'une vente est les deux à la fois.
     *
     * Bornée à `[0, cash]` : un apport encore entièrement immobilisé en titres ne doit pas rendre
     * l'investi négatif sur un compte espèces vide.
     */
    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user);

        return new ClassSnapshotData(
            value: $overview->cash,
            invested: $this->investedOf($overview->netContributions, $overview->totalCost, $overview->cash),
        );
    }

    /**
     * Une grille faite des dates de mouvement d'espèces : le solde ne dépend d'aucun cours de
     * marché, contrairement au coût de revient des titres détenus, contrairement à `totalCost` —
     * une grille calée sur les prix laisserait sans aucun point un utilisateur qui ne fait que
     * déposer, sans jamais acheter de titre.
     */
    public function seriesFor(int $userId): ClassSeriesData
    {
        $movements = ($this->movements)($userId);

        if ($movements === []) {
            return ClassSeriesData::empty();
        }

        $labels = array_values(array_unique(array_map(
            fn (CashMovementData $movement): string => $movement->date,
            $movements,
        )));
        sort($labels);

        /**
         * Le coût de revient vient de sa propre série, hebdomadaire et sans filtre d'exposition ;
         * `SeriesAligner::onto()` la reporte sur la grille des mouvements — vide pour un
         * utilisateur qui ne fait que déposer, ce qui reporte alors un coût nul à chaque date, la
         * seule valeur correcte.
         */
        $costSeries = ($this->evolution)($userId, null, ValuationGranularity::Week);
        $costs = $this->aligner->onto(
            $labels,
            $costSeries->labels,
            $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->invested, $costSeries->perAsset),
                count($costSeries->labels),
            ),
        );

        $values = [];
        $invested = [];

        foreach ($labels as $index => $date) {
            $upToDate = array_values(array_filter(
                $movements,
                fn (CashMovementData $movement): bool => $movement->date <= $date,
            ));

            $balance = $this->balanceAt($upToDate, $date);
            $netContributions = $this->ledger->netContributions($upToDate)['total'];

            $values[] = $balance;
            $invested[] = $this->investedOf($netContributions, $costs[$index], $balance);
        }

        return new ClassSeriesData(labels: $labels, value: $values, invested: $invested);
    }

    /** @param  list<CashMovementData>  $movements */
    private function balanceAt(array $movements, string $date): float
    {
        return round(array_sum(array_map(
            fn (CashMovementData $movement): float => $movement->date <= $date ? $movement->delta : 0.0,
            $movements,
        )), 2);
    }

    /**
     * `investi(Liquidités) = borne(apports nets − coût de revient des titres détenus, 0, solde)`.
     */
    private function investedOf(float $netContributions, float $costOfHoldings, float $cash): float
    {
        return round(min(max($netContributions - $costOfHoldings, 0.0), $cash), 2);
    }
}
