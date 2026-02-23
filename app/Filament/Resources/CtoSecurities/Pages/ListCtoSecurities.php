<?php

namespace App\Filament\Resources\CtoSecurities\Pages;

use App\Filament\Resources\CtoSecurities\CtoSecurityResource;
use App\Filament\Resources\Securities\Pages\ListSecurities;

class ListCtoSecurities extends ListSecurities
{
    protected static string $resource = CtoSecurityResource::class;

    protected static ?string $title = 'CTO';
}
