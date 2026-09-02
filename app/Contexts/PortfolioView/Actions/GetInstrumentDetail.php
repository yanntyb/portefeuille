<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\PortfolioView\Datas\InstrumentDetailData;
use App\Contexts\PortfolioView\Datas\SectorWeightData;

/**
 * La fiche d'un instrument : ses métadonnées, son dernier cours, la position du porteur si elle
 * est valorisée, son journal et ses secteurs. Lit Market par ses modèles et son dépôt de cours,
 * Portfolio par ses actions.
 */
class GetInstrumentDetail
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private GetTransactionJournal $journal,
    ) {}

    public function __invoke(int $userId, int $instrumentId): ?InstrumentDetailData
    {
        $instrument = Instrument::query()->find($instrumentId);

        if ($instrument === null) {
            return null;
        }

        $latest = $this->prices->latestForAsset($instrumentId);
        $position = ($this->positions)($userId)[$instrumentId] ?? null;

        if ($position !== null && $position->marketValue === null) {
            $position = null;
        }

        return new InstrumentDetailData(
            id: $instrument->id,
            name: (string) $instrument->name,
            ticker: $instrument->ticker,
            isin: $instrument->isin,
            type: $instrument->type,
            assetClass: $instrument->asset_class,
            lastPrice: $latest !== null ? (float) $latest->close : null,
            lastPriceDate: $latest !== null ? $latest->date->format('Y-m-d') : null,
            position: $position,
            transactions: $this->journal->forAsset($userId, $instrumentId),
            sectors: $this->sectorsOf($instrumentId),
        );
    }

    /** @return list<SectorWeightData> */
    private function sectorsOf(int $instrumentId): array
    {
        return SectorAllocation::query()
            ->where('asset_id', $instrumentId)
            ->orderByDesc('weight')
            ->get()
            ->map(fn (SectorAllocation $allocation): SectorWeightData => new SectorWeightData(
                label: $allocation->sector->getLabel(),
                weight: (float) $allocation->weight,
            ))
            ->values()
            ->all();
    }
}
