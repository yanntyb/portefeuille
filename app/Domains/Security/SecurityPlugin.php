<?php

namespace App\Domains\Security;

class SecurityPlugin
{
    public const RESOURCES = [
        'App\Domains\Security\Filament\Resources\AllSecurities\AllSecurityResource',
    ];

    public const PAGES = [];

    public const WIDGETS = [];

    public const PATHS = [
        'resources' => 'app/Domains/Security/Filament/Resources',
        'pages' => 'app/Domains/Security/Filament/Pages',
        'widgets' => 'app/Domains/Security/Filament/Widgets',
    ];

    public const NAMESPACES = [
        'resources' => 'App\Domains\Security\Filament\Resources',
        'pages' => 'App\Domains\Security\Filament\Pages',
        'widgets' => 'App\Domains\Security\Filament\Widgets',
    ];
}
