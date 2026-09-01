<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
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
use App\Contexts\Wealth\Services\InvestedCapital;
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
        private InvestedCapital $capital,
    ) {}

    /**
     * `value` est le solde toutes enveloppes confondues. `invested` est tout ce que les expositions
     * n'immobilisent plus : l'apport suit l'argent, imputé à l'exposition tant qu'il est en titres,
     * rendu aux liquidités dès qu'elles sont vendues. L'étiquette d'origine du FIFO
     * (`CashLedger::compositionAt()`) ne conviendrait pas — elle dit d'où vient un euro, pas s'il
     * est capital ou gain, et le produit d'une vente est les deux à la fois.
     *
     * Les investis d'exposition se relisent un par un plutôt que de se déduire de `totalCost` : ce
     * sont exactement ceux que `PortfolioAssetClass` déclare, et l'invariant du chantier — la somme
     * des investis de toutes les classes fait les apports nets — ne tient qu'à ce prix.
     * `GetPortfolioOverview` est liée en `scoped` et mémoïse ses lignes : les quatre lectures
     * supplémentaires ne rouvrent pas le portefeuille.
     */
    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user);

        $exposuresInvested = 0.0;

        foreach (AssetClass::cases() as $exposure) {
            $scoped = ($this->overview)($user, [$exposure]);
            $exposuresInvested = round(
                $exposuresInvested + $this->capital->forExposure($scoped->netContributions, $scoped->totalCost),
                2,
            );
        }

        return new ClassSnapshotData(
            value: $overview->cash,
            invested: $this->capital->forCash($overview->netContributions, $exposuresInvested, $overview->cash),
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

        $points = $this->ledger->timeline($movements, $labels);

        $values = [];
        $invested = [];

        foreach (array_keys($labels) as $index) {
            $values[] = $points[$index]['balance'];
            $invested[] = $this->capital->forCash($points[$index]['netContributions'], $costs[$index], $points[$index]['balance']);
        }

        return new ClassSeriesData(labels: $labels, value: $values, invested: $invested);
    }
}
