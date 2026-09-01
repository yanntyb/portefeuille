<?php

namespace App\Contexts\Income\Sources\Dividend;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Services\RollingWindow;
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
        private RollingWindow $window,
    ) {}

    public function source(): IncomeSource
    {
        return IncomeSource::Dividend;
    }

    /**
     * Un détachement encaissé sort de la dérivation : c'est la transaction qui compte à sa place,
     * jamais les deux — la clé de dédoublonnage est `(assetId, walletId, exDate)`.
     *
     * @return list<IncomeReceiptData>
     */
    public function receiptsFor(int $userId): array
    {
        $assetIds = $this->positions->assetIdsFor($userId);
        $confirmed = $this->positions->confirmedDividendsFor($userId);

        if ($assetIds === [] && $confirmed === []) {
            return [];
        }

        $derived = $assetIds === [] ? [] : $this->calculator->receipts(
            $this->positions->transactionsFor($userId),
            $this->dividends->forAssets($assetIds),
        );

        /** @var array<string, true> $confirmedKeys */
        $confirmedKeys = [];

        foreach ($confirmed as $dividend) {
            $confirmedKeys[$this->key($dividend->assetId, $dividend->walletId, $dividend->exDate)] = true;
        }

        $derived = array_values(array_filter(
            $derived,
            fn (DividendReceiptData $receipt): bool => ! isset($confirmedKeys[$this->key($receipt->assetId, $receipt->walletId, $receipt->exDate)]),
        ));

        $names = $this->dividends->namesFor(array_unique([
            ...$assetIds,
            ...array_map(fn ($dividend): int => $dividend->assetId, $confirmed),
        ]));

        $receipts = array_map(fn (DividendReceiptData $receipt): IncomeReceiptData => new IncomeReceiptData(
            source: IncomeSource::Dividend,
            date: Carbon::parse($receipt->exDate),
            amount: $receipt->amount,
            assetId: $receipt->assetId,
            label: $names[$receipt->assetId] ?? null,
        ), $derived);

        foreach ($confirmed as $dividend) {
            $receipts[] = new IncomeReceiptData(
                source: IncomeSource::Dividend,
                date: Carbon::parse($dividend->exDate),
                amount: $dividend->amount,
                assetId: $dividend->assetId,
                label: $names[$dividend->assetId] ?? null,
            );
        }

        return $receipts;
    }

    /** Clé de dédoublonnage entre un reçu dérivé et une transaction de dividende encaissée. */
    private function key(int $assetId, int $walletId, string $exDate): string
    {
        return "{$assetId}|{$walletId}|{$exDate}";
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
            $this->window->slidingDays(Carbon::now()),
        );

        return round(array_sum($estimates), 2);
    }
}
