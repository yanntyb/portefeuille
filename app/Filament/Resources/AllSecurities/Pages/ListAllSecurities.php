<?php

namespace App\Filament\Resources\AllSecurities\Pages;

use App\Filament\Resources\AllSecurities\AllSecurityResource;
use Filament\Resources\Pages\ListRecords;

class ListAllSecurities extends ListRecords
{
    protected static string $resource = AllSecurityResource::class;

    protected static ?string $title = 'Tous les titres';
}
