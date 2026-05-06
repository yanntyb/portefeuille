<?php

namespace App\Domains\Security\Filament\Resources\AllSecurities\Pages;

use App\Domains\Security\Filament\Resources\AllSecurities\AllSecurityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAllSecurity extends CreateRecord
{
    protected static string $resource = AllSecurityResource::class;
}
