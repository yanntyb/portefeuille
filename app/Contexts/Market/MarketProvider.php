<?php

namespace App\Contexts\Market;

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\PriceProviderPort;
use App\Contexts\Market\Ports\SectorProviderPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class MarketProvider extends ServiceProvider
{
    /**
     * @param  class-string<InstrumentRepositoryContract>  $instrumentRepository
     * @param  class-string<PriceRepositoryContract>  $priceRepository
     * @param  class-string<SectorRepositoryContract>  $sectorRepository
     * @param  class-string<PriceProviderPort>  $priceProvider
     * @param  class-string<PriceFeedPort>  $priceFeed
     * @param  class-string<SectorProviderPort>  $sectorProvider
     * @param  class-string<DividendRepositoryContract>  $dividendRepository
     * @param  class-string<DividendFeedPort>  $dividendFeed
     * @param  class-string<MarketSyncStatePort>  $syncState
     */
    public static function registers(
        Application $app,
        string $instrumentRepository,
        string $priceRepository,
        string $sectorRepository,
        string $priceProvider,
        string $priceFeed,
        string $sectorProvider,
        string $dividendRepository,
        string $dividendFeed,
        string $syncState,
    ): void {
        $app->bind(InstrumentRepositoryContract::class, $instrumentRepository);
        $app->bind(PriceRepositoryContract::class, $priceRepository);
        $app->bind(SectorRepositoryContract::class, $sectorRepository);
        $app->bind(PriceProviderPort::class, $priceProvider);
        $app->bind(PriceFeedPort::class, $priceFeed);
        $app->bind(SectorProviderPort::class, $sectorProvider);
        $app->bind(DividendRepositoryContract::class, $dividendRepository);
        $app->bind(DividendFeedPort::class, $dividendFeed);
        $app->bind(MarketSyncStatePort::class, $syncState);
    }
}
