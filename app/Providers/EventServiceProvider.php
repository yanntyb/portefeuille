<?php

namespace App\Providers;

use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Listeners\CalculateRealizedGainListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TransactionCreated::class => [
            CalculateRealizedGainListener::class,
        ],
    ];
}
