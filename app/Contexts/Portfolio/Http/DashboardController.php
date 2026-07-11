<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
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

        return Inertia::render('Dashboard', [
            'overview' => $overview,
            'valuationSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioValuationSeries::class)($user->id)
                : ValuationSeriesData::empty()),
        ]);
    }
}
