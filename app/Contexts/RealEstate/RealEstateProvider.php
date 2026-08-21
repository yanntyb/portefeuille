<?php

namespace App\Contexts\RealEstate;

use App\Contexts\RealEstate\Ports\RealEstateCachePort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RealEstateProvider extends ServiceProvider
{
    /** @param  class-string<RealEstateCachePort>  $cache */
    public static function registers(Application $app, string $cache): void
    {
        /** L'empreinte des données est calculée une fois par requête : `scoped()` et non `singleton()`. */
        $app->scoped(RealEstateCachePort::class, $cache);
    }
}
