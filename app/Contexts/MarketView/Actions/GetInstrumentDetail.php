<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Datas\InstrumentDetailData;
use App\Contexts\MarketView\Datas\InstrumentMetaData;
use App\Contexts\MarketView\Datas\PositionData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\TransactionsPort;

class GetInstrumentDetail
{
    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(int $userId, int $instrumentId): ?InstrumentDetailData
    {
        $meta = $this->market->findInstrument($instrumentId);

        if ($meta === null) {
            return null;
        }

        $holding = $this->holdings->holdingFor($userId, $instrumentId);

        return new InstrumentDetailData(
            id: $meta->id,
            name: $meta->name,
            ticker: $meta->ticker,
            isin: $meta->isin,
            type: $meta->type,
            assetClass: $meta->assetClass,
            lastPrice: $meta->lastPrice,
            lastPriceDate: $meta->lastPriceDate,
            position: $this->buildPosition($holding, $meta),
            transactions: $this->transactions->transactionsFor($userId, $instrumentId),
            sectors: $this->market->sectors($instrumentId),
        );
    }

    private function buildPosition(?HoldingSnapshotData $holding, InstrumentMetaData $meta): ?PositionData
    {
        if ($holding === null || $meta->lastPrice === null) {
            return null;
        }

        $marketValue = $holding->quantity * $meta->lastPrice;
        $cost = $holding->avgCost !== null ? $holding->quantity * $holding->avgCost : null;
        $gain = $cost !== null ? $marketValue - $cost : null;
        $gainPct = ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;

        return new PositionData(
            quantity: $holding->quantity,
            avgCost: $holding->avgCost,
            marketValue: $marketValue,
            gain: $gain,
            gainPct: $gainPct,
        );
    }
}
