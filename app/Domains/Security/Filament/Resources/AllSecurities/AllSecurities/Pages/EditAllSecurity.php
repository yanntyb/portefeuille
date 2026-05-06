<?php

namespace App\Filament\Resources\AllSecurities\Pages;

use App\Domains\Security\Filament\Resources\AllSecurities\AllSecurityResource;
use App\Domains\Security\Filament\Resources\SecurityBase\Pages\EditSecurity;

class EditAllSecurity extends EditSecurity
{
    protected static string $resource = AllSecurityResource::class;
}
