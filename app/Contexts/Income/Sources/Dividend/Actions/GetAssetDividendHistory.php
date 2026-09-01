<?php

namespace App\Contexts\Income\Sources\Dividend\Actions;

use App\Contexts\Income\Services\RollingWindow;
use App\Contexts\Income\Sources\Dividend\Datas\AssetDividendHistoryData;
use App\Contexts\Income\Sources\Dividend\Datas\ConfirmedDividendData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Income\Sources\Dividend\Services\ConfirmedDividendSubstitution;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use App\Contexts\Income\Sources\Dividend\Services\DividendProjector;
use Illuminate\Support\Carbon;

class GetAssetDividendHistory
{
    public function __construct(
        private DividendHistoryPort $dividends,
        private PositionHistoryPort $positions,
        private DividendCalculator $calculator,
        private DividendProjector $projector,
        private RollingWindow $window,
        private ConfirmedDividendSubstitution $substitution,
    ) {}

    /**
     * Dividendes perçus sur un seul instrument, avec son rendement sur coût.
     *
     * Vit dans le dossier de la source dividende et non dans le noyau : « par instrument » n'a
     * pas de sens pour un revenu qui ne porte sur aucun titre.
     *
     * Un détachement encaissé y est lu depuis sa transaction, jamais recalculé — même règle et
     * même site (`ConfirmedDividendSubstitution`) que `DividendIncomeSource`, sans quoi la fiche
     * d'un actif et le tableau de bord des revenus diraient deux montants pour le même fait.
     */
    public function __invoke(int $userId, int $assetId): AssetDividendHistoryData
    {
        $movements = array_values(array_filter(
            $this->positions->transactionsFor($userId),
            fn (PositionRecordData $movement): bool => $movement->assetId === $assetId,
        ));

        $dividends = $this->dividends->forAssets([$assetId]);
        $derived = $this->calculator->receipts($movements, $dividends);

        $confirmed = array_values(array_filter(
            $this->positions->confirmedDividendsFor($userId),
            fn (ConfirmedDividendData $dividend): bool => $dividend->assetId === $assetId,
        ));

        $receipts = $this->substitution->apply($derived, $confirmed);

        $since = $this->window->slidingDays(Carbon::now());
        $position = $this->positions->positionFor($userId, $assetId);
        $estimatedAnnual = $this->estimatedAnnual($position, $dividends, $assetId, $since);

        if ($receipts === [] && $estimatedAnnual <= 0.0) {
            return AssetDividendHistoryData::empty();
        }

        $total = 0.0;
        $last12Months = 0.0;

        foreach ($receipts as $receipt) {
            $total += $receipt->amount;

            if (Carbon::parse($receipt->exDate)->gte($since)) {
                $last12Months += $receipt->amount;
            }
        }

        return new AssetDividendHistoryData(
            receipts: $receipts,
            totalReceived: round($total, 2),
            last12Months: round($last12Months, 2),
            estimatedAnnual: $estimatedAnnual,
            yieldOnCost: $this->yieldOnCost($position, $last12Months),
        );
    }

    /**
     * Revenu attendu sur les douze prochains mois pour la position courante.
     *
     * @param  list<DividendRecordData>  $dividends
     */
    private function estimatedAnnual(?PositionSnapshotData $position, array $dividends, int $assetId, Carbon $since): float
    {
        if ($position === null) {
            return 0.0;
        }

        return $this->projector->annualEstimates($dividends, [$assetId => $position->quantity], $since)[$assetId] ?? 0.0;
    }

    /** Perçu sur douze mois rapporté au coût de la position courante, en pourcentage. */
    private function yieldOnCost(?PositionSnapshotData $position, float $last12Months): ?float
    {
        if ($position === null || $position->avgCost === null) {
            return null;
        }

        $cost = $position->avgCost * $position->quantity;

        return $cost > 0.0 ? round($last12Months / $cost * 100, 2) : null;
    }
}
