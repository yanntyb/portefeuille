<?php

namespace App\Providers;

use App\Services\DashboardDataProvider;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(DashboardDataProvider::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
