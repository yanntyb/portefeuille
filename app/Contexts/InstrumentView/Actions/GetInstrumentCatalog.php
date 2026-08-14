<?php

namespace App\Contexts\InstrumentView\Actions;

use App\Contexts\InstrumentView\Datas\CatalogLineData;
use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
use App\Contexts\InstrumentView\Datas\InstrumentCatalogData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;

class GetInstrumentCatalog
{
    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
    ) {}

    public function __invoke(int $userId): InstrumentCatalogData
    {
        $heldByAsset = [];
        foreach ($this->holdings->holdingsFor($userId) as $snapshot) {
            $heldByAsset[$snapshot->assetId] = $snapshot;
        }

        $lines = [];
        foreach ($this->market->listInstruments() as $summary) {
            $lines[] = $this->toLine($summary, $heldByAsset[$summary->id] ?? null);
        }

        return new InstrumentCatalogData(lines: $lines);
    }

    private function toLine(InstrumentSummaryData $summary, ?HoldingSnapshotData $holding): CatalogLineData
    {
        $lastPrice = $this->market->latestPrice($summary->id);
        $quantity = $holding?->quantity;
        $marketValue = ($quantity !== null && $lastPrice !== null) ? $quantity * $lastPrice : null;

        return new CatalogLineData(
            id: $summary->id,
            name: $summary->name,
            ticker: $summary->ticker,
            isin: $summary->isin,
            type: $summary->type,
            lastPrice: $lastPrice,
            held: $holding !== null,
            quantity: $quantity,
            marketValue: $marketValue,
        );
    }
}
