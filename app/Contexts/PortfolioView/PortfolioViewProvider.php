<?php

namespace App\Contexts\PortfolioView;

use App\Contexts\PortfolioView\Ports\AccountsPort;
use App\Contexts\PortfolioView\Ports\BasketAnalysisPort;
use App\Contexts\PortfolioView\Ports\HoldingsPort;
use App\Contexts\PortfolioView\Ports\IncomePort;
use App\Contexts\PortfolioView\Ports\InstrumentAnalysisPort;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PortfolioViewProvider extends ServiceProvider
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
     * @param  class-string<BasketAnalysisPort>  $basketAnalysis
     * @param  class-string<AccountsPort>  $accounts
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
        string $basketAnalysis,
        string $accounts,
    ): void {
        $app->bind(MarketDataPort::class, $marketData);
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(TransactionsPort::class, $transactions);
        $app->bind(SectorBreakdownPort::class, $sectorBreakdown);
        $app->bind(IncomePort::class, $income);
        $app->bind(PortfolioOverviewPort::class, $portfolioOverview);
        $app->bind(ValuationPort::class, $valuation);
        $app->bind(InstrumentAnalysisPort::class, $instrumentAnalysis);
        $app->bind(BasketAnalysisPort::class, $basketAnalysis);
        $app->bind(AccountsPort::class, $accounts);
    }
}
