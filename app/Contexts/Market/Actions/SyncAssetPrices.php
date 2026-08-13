<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use Illuminate\Support\Collection;

class SyncAssetPrices
{
    private const FALLBACK_MONTHS = 12;

    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private PriceFeedPort $feed,
        private PriceRepositoryContract $prices,
    ) {}

    /**
     * Fetch and persist the daily prices of every supported asset.
     *
     * Without $since, each asset resumes at its last stored price.
     */
    public function __invoke(?int $assetId = null, ?string $since = null): PriceSyncReportData
    {
        $instruments = $this->instrumentsToSync($assetId)->filter(
            fn (Instrument $instrument): bool => $instrument->ticker !== null
                && $this->feed->supports($instrument->type),
        );

        if ($instruments->isEmpty()) {
            return new PriceSyncReportData;
        }

        $requests = $instruments->map(fn (Instrument $instrument): PriceRequestData => new PriceRequestData(
            ticker: $instrument->ticker,
            startDate: $this->startDateFor($instrument, $since),
            endDate: now()->format('Y-m-d'),
        ))->values()->all();

        try {
            $fetched = $this->feed->fetchPrices($requests);
        } catch (PriceFeedException) {
            return new PriceSyncReportData(failed: $instruments->pluck('ticker')->values()->all());
        }

        $synced = [];

        foreach ($instruments as $instrument) {
            $synced[$instrument->ticker] = $this->prices->upsertForAsset(
                $instrument->id,
                $fetched[$instrument->ticker] ?? [],
            );
        }

        return new PriceSyncReportData(synced: $synced);
    }

    private function startDateFor(Instrument $instrument, ?string $since): string
    {
        if ($since !== null) {
            return $since;
        }

        $latest = $this->prices->latestForAsset($instrument->id);

        return $latest !== null
            ? $latest->date->format('Y-m-d')
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
