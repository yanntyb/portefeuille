<?php

namespace App\Contexts\Income\Sources\Dividend;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use App\Contexts\Income\Sources\Dividend\Services\DividendProjector;
use Illuminate\Support\Carbon;

class DividendIncomeSource implements IncomeSourcePort
{
    public function __construct(
        private DividendHistoryPort $dividends,
        private PositionHistoryPort $positions,
        private DividendCalculator $calculator,
        private DividendProjector $projector,
    ) {}

    public function source(): IncomeSource
    {
        return IncomeSource::Dividend;
    }

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array
    {
        $assetIds = $this->positions->assetIdsFor($userId);

        if ($assetIds === []) {
            return [];
        }

        $receipts = $this->calculator->receipts(
            $this->positions->transactionsFor($userId),
            $this->dividends->forAssets($assetIds),
        );

        $names = $this->dividends->namesFor($assetIds);

        return array_map(fn (DividendReceiptData $receipt): IncomeReceiptData => new IncomeReceiptData(
            source: IncomeSource::Dividend,
            date: Carbon::parse($receipt->exDate),
            amount: $receipt->amount,
            assetId: $receipt->assetId,
            label: $names[$receipt->assetId] ?? null,
        ), $receipts);
    }

    /**
     * Dividendes attendus sur les douze prochains mois pour les positions encore détenues.
     *
     * Ne repart pas des reçus : un titre acheté après son dernier détachement n'en a aucun et
     * attend pourtant le prochain versement.
     */
    public function projectedAnnualFor(int $userId): float
    {
        $positions = $this->positions->positionsFor($userId);

        if ($positions === []) {
            return 0.0;
        }

        $estimates = $this->projector->annualEstimates(
            $this->dividends->forAssets(array_keys($positions)),
            array_map(fn (PositionSnapshotData $position): float => $position->quantity, $positions),
            Carbon::now()->subYear()->startOfDay(),
        );

        return round(array_sum($estimates), 2);
    }
}
