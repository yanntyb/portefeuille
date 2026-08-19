<?php

namespace App\Contexts\Income\Sources\Dividend\Actions;

use App\Contexts\Income\Sources\Dividend\Datas\AssetDividendHistoryData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use Illuminate\Support\Carbon;

class GetAssetDividendHistory
{
    public function __construct(
        private DividendHistoryPort $dividends,
        private PositionHistoryPort $positions,
        private DividendCalculator $calculator,
    ) {}

    /**
     * Dividendes perçus sur un seul instrument, avec son rendement sur coût.
     *
     * Vit dans le dossier de la source dividende et non dans le noyau : « par instrument » n'a
     * pas de sens pour un revenu qui ne porte sur aucun titre.
     */
    public function __invoke(int $userId, int $assetId): AssetDividendHistoryData
    {
        $movements = array_values(array_filter(
            $this->positions->transactionsFor($userId),
            fn (PositionRecordData $movement): bool => $movement->assetId === $assetId,
        ));

        $receipts = $this->calculator->receipts($movements, $this->dividends->forAssets([$assetId]));

        if ($receipts === []) {
            return AssetDividendHistoryData::empty();
        }

        $since = Carbon::now()->subYear();
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
            yieldOnCost: $this->yieldOnCost($userId, $assetId, $last12Months),
        );
    }

    /** Perçu sur douze mois rapporté au coût de la position courante, en pourcentage. */
    private function yieldOnCost(int $userId, int $assetId, float $last12Months): ?float
    {
        $position = $this->positions->positionFor($userId, $assetId);

        if ($position === null || $position->avgCost === null) {
            return null;
        }

        $cost = $position->avgCost * $position->quantity;

        return $cost > 0.0 ? round($last12Months / $cost * 100, 2) : null;
    }
}
