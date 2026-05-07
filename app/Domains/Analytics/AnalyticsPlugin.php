<?php

namespace App\Domains\Analytics;

class AnalyticsPlugin
{
    public const RESOURCES = [];

    public const PAGES = [];

    public const WIDGETS = [];

    public const PATHS = [
        'resources' => 'app/Domains/Analytics/Filament/Resources',
        'pages' => 'app/Domains/Analytics/Filament/Pages',
        'widgets' => 'app/Domains/Analytics/Filament/Widgets',
    ];

    public const NAMESPACES = [
        'resources' => 'App\Domains\Analytics\Filament\Resources',
        'pages' => 'App\Domains\Analytics\Filament\Pages',
        'widgets' => 'App\Domains\Analytics\Filament\Widgets',
    ];
}
