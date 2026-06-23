<?php

namespace App\Contexts\Market;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Ports\PriceProviderPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class MarketProvider extends ServiceProvider
{
    /**
     * @param  class-string<InstrumentRepositoryContract>  $instrumentRepository
     * @param  class-string<PriceRepositoryContract>  $priceRepository
     * @param  class-string<PriceProviderPort>  $priceProvider
     */
    public static function registers(
        Application $app,
        string $instrumentRepository,
        string $priceRepository,
        string $priceProvider,
    ): void {
        $app->bind(InstrumentRepositoryContract::class, $instrumentRepository);
        $app->bind(PriceRepositoryContract::class, $priceRepository);
        $app->bind(PriceProviderPort::class, $priceProvider);
    }
}
