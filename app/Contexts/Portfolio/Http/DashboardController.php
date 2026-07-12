<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildInvestedByAssetSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(private GetPortfolioOverview $getPortfolioOverview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        $range = ValuationRange::fromRequest(request()->query('range'));
        $granularity = ValuationGranularity::fromRequest(request()->query('granularity'));

        return Inertia::render('Dashboard', [
            'overview' => $overview,
            'valuationRange' => $range->value,
            'valuationGranularity' => $granularity->value,
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : []),
            'valuationSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioValuationSeries::class)($user->id, $range, $granularity)
                : ValuationSeriesData::empty()),
            'investedByAsset' => Inertia::defer(fn () => $user !== null
                ? app(BuildInvestedByAssetSeries::class)($user->id, $range, $granularity)
                : InvestedByAssetSeriesData::empty()),
        ]);
    }
}
