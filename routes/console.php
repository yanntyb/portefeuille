<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Domains\Security\Commands\FetchSecurityPricesCommand;
use App\Domains\Security\Commands\FetchSecuritySectorsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register domain commands
Artisan::register(FetchSecurityPricesCommand::class);
Artisan::register(FetchSecuritySectorsCommand::class);
