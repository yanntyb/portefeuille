<?php

namespace App\Domains\Portfolio;

class PortfolioPlugin
{
    public const RESOURCES = [
        'App\Domains\Portfolio\Filament\Resources\Transactions\TransactionResource',
        'App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource',
        'App\Domains\Portfolio\Filament\Resources\WalletSecurities\WalletSecurityResource',
    ];

    public const PAGES = [];

    public const WIDGETS = [];

    public const PATHS = [
        'resources' => 'app/Domains/Portfolio/Filament/Resources',
        'pages' => 'app/Domains/Portfolio/Filament/Pages',
        'widgets' => 'app/Domains/Portfolio/Filament/Widgets',
    ];

    public const NAMESPACES = [
        'resources' => 'App\Domains\Portfolio\Filament\Resources',
        'pages' => 'App\Domains\Portfolio\Filament\Pages',
        'widgets' => 'App\Domains\Portfolio\Filament\Widgets',
    ];
}
