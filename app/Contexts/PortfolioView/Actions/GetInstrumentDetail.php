<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\PortfolioView\Datas\InstrumentDetailData;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;

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
