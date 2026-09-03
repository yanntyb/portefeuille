<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Market\Ports\MarketSyncStatePort;
use App\Contexts\Wealth\Pages\DashboardPage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(
        private DashboardPage $page,
        private MarketSyncStatePort $syncState,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'sync' => $this->syncState->current(),
            ...$this->page->for(auth()->id() ?? 0)->toInertiaProps(),
        ]);
    }
}
