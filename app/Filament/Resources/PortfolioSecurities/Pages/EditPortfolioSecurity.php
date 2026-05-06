<?php

namespace App\Filament\Resources\PortfolioSecurities\Pages;

use App\Domains\Security\Filament\Resources\SecurityBase\Pages\EditSecurity;
use App\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource;
use Illuminate\Contracts\Support\Htmlable;

class EditPortfolioSecurity extends EditSecurity
{
    protected static string $resource = PortfolioSecurityResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? $this->record->ticker ?? $this->record->isin;
    }
}
