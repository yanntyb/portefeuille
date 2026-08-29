<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\MarketView\Datas\InstrumentDetailData;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\TransactionsPort;

class GetInstrumentDetail
{
    public function __construct(
        private MarketDataPort $market,
        private PortfolioOverviewPort $overview,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(int $userId, int $instrumentId): ?InstrumentDetailData
    {
        $meta = $this->market->findInstrument($instrumentId);

        if ($meta === null) {
            return null;
        }

        $position = $this->overview->positionFor($userId, $instrumentId);

        if ($position !== null && $position->marketValue === null) {
            $position = null;
        }

        return new InstrumentDetailData(
            id: $meta->id,
            name: $meta->name,
            ticker: $meta->ticker,
            isin: $meta->isin,
            type: $meta->type,
            assetClass: $meta->assetClass,
            lastPrice: $meta->lastPrice,
            lastPriceDate: $meta->lastPriceDate,
            position: $position,
            transactions: $this->transactions->transactionsFor($userId, $instrumentId),
            sectors: $this->market->sectors($instrumentId),
        );
    }
}
