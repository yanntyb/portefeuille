<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\SectorProviderPort;
use Illuminate\Support\Collection;

class SyncAssetSectors
{
    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private SectorProviderPort $provider,
        private SectorRepositoryContract $sectors,
    ) {}

    /**
     * Fetch and persist the sector allocations of every supported asset.
     *
     * @return array<string, int> number of stored sectors, keyed by ticker
     */
    public function __invoke(?int $assetId = null): array
    {
        $synced = [];

        foreach ($this->instrumentsToSync($assetId) as $instrument) {
            if ($instrument->ticker === null || ! $this->provider->supports($instrument->type)) {
                continue;
            }

            $allocations = $this->provider->getSectorAllocations($instrument->ticker, $instrument->type);

            if ($allocations === []) {
                continue;
            }

            $this->sectors->replaceForAsset($instrument->id, $allocations);
            $synced[$instrument->ticker] = count($allocations);
        }

        return $synced;
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
