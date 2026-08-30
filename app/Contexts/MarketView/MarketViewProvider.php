<?php

namespace App\Contexts\MarketView;

use App\Contexts\MarketView\Ports\ClassAnalysisPort;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\InstrumentAnalysisPort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\SectorBreakdownPort;
use App\Contexts\MarketView\Ports\TransactionsPort;
use App\Contexts\MarketView\Ports\ValuationPort;
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
     * @param  class-string<PortfolioOverviewPort>  $portfolioOverview
     * @param  class-string<ValuationPort>  $valuation
     * @param  class-string<InstrumentAnalysisPort>  $instrumentAnalysis
     * @param  class-string<ClassAnalysisPort>  $classAnalysis
     */
    public static function registers(
        Application $app,
        string $marketData,
        string $holdings,
        string $transactions,
        string $sectorBreakdown,
        string $income,
        string $portfolioOverview,
        string $valuation,
        string $instrumentAnalysis,
        string $classAnalysis,
    ): void {
        $app->bind(MarketDataPort::class, $marketData);
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(TransactionsPort::class, $transactions);
        $app->bind(SectorBreakdownPort::class, $sectorBreakdown);
        $app->bind(IncomePort::class, $income);
        $app->bind(PortfolioOverviewPort::class, $portfolioOverview);
        $app->bind(ValuationPort::class, $valuation);
        $app->bind(InstrumentAnalysisPort::class, $instrumentAnalysis);
        $app->bind(ClassAnalysisPort::class, $classAnalysis);
    }
}
