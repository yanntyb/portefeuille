<?php

namespace App\Filament\Resources\AllSecurities\Pages;

use App\Domains\Security\Filament\Resources\AllSecurities\AllSecurityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllSecurities extends ListRecords
{
    protected static string $resource = AllSecurityResource::class;

    protected static ?string $title = 'Tous les titres';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
