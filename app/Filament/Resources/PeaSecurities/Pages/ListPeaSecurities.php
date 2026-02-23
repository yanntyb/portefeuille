<?php

namespace App\Filament\Resources\PeaSecurities\Pages;

use App\Filament\Resources\PeaSecurities\PeaSecurityResource;
use App\Filament\Resources\Securities\Pages\ListSecurities;

class ListPeaSecurities extends ListSecurities
{
    protected static string $resource = PeaSecurityResource::class;

    protected static ?string $title = 'PEA';
}
