<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
use App\Contexts\Market\Ports\DividendFeedPort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncAssetDividends
{
    /**
     * Cinq ans et non un an comme les cours : l'historique perçu doit couvrir la vie des
     * positions, pas la dernière année.
     */
    private const FALLBACK_MONTHS = 60;

    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private DividendFeedPort $feed,
        private DividendRepositoryContract $dividends,
    ) {}

    /**
     * Récupère et persiste les détachements de chaque instrument distribuant.
     *
     * Sans $since, chaque instrument reprend à son dernier détachement stocké.
     */
    public function __invoke(?int $assetId = null, ?string $since = null): DividendSyncReportData
    {
        $instruments = $this->instrumentsToSync($assetId)->filter(
            fn (Instrument $instrument): bool => $instrument->ticker !== null
                && $this->feed->supportsDividendFeed($instrument->type),
        );

        if ($instruments->isEmpty()) {
            return new DividendSyncReportData;
        }

        $requests = $instruments->map(fn (Instrument $instrument): DividendRequestData => new DividendRequestData(
            ticker: $instrument->ticker,
            startDate: $this->startDateFor($instrument, $since),
            endDate: now()->format('Y-m-d'),
        ))->values()->all();

        try {
            $fetched = $this->feed->fetchDividends($requests);
        } catch (DividendFeedException $exception) {
            $tickers = $instruments->pluck('ticker')->values()->all();

            Log::error('Dividend sync failed for every asset.', [
                'error' => $exception->getMessage(),
                'tickers' => $tickers,
            ]);

            return new DividendSyncReportData(failed: $tickers, error: $exception->reason);
        }

        $synced = [];

        foreach ($instruments as $instrument) {
            $synced[$instrument->ticker] = $this->dividends->upsertForAsset(
                $instrument->id,
                $fetched[$instrument->ticker] ?? [],
            );
        }

        return new DividendSyncReportData(synced: $synced);
    }

    private function startDateFor(Instrument $instrument, ?string $since): string
    {
        if ($since !== null) {
            return $since;
        }

        $latest = $this->dividends->latestForAsset($instrument->id);

        return $latest !== null
            ? $latest->ex_date->format('Y-m-d')
            : now()->subMonths(self::FALLBACK_MONTHS)->format('Y-m-d');
    }

    /**
     * @return Collection<int, Instrument>
     */
    private function instrumentsToSync(?int $assetId): Collection
    {
        if ($assetId === null) {
            return $this->instruments->findAll();
        }

        $instrument = $this->instruments->findById($assetId);

        return $instrument !== null ? collect([$instrument]) : collect();
    }
}
