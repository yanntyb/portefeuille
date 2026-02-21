<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiClient::class, function () {
            return ApiClientFactory::createApiClient();
        });
    }

    public function boot(): void
    {
        //
    }
}
