<?php

namespace App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\Pages;

use App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePortfolioSecurity extends CreateRecord
{
    protected static string $resource = PortfolioSecurityResource::class;
}
