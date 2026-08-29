<?php

namespace App\Contexts\MarketView;

use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\TransactionsPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class MarketViewProvider extends ServiceProvider
{
    /**
     * @param  class-string<MarketDataPort>  $marketData
     * @param  class-string<HoldingsPort>  $holdings
     * @param  class-string<TransactionsPort>  $transactions
     * @param  class-string<SectorBreakdownPort>  $sectorBreakdown
     * @param  class-string<IncomePort>  $income
     */
    public static function registers(
        Application $app,
        string $marketData,
        string $holdings,
        string $transactions,
        string $sectorBreakdown,
        string $income,
    ): void {
        $app->bind(MarketDataPort::class, $marketData);
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(TransactionsPort::class, $transactions);
        $app->bind(SectorBreakdownPort::class, $sectorBreakdown);
        $app->bind(IncomePort::class, $income);
    }
}
