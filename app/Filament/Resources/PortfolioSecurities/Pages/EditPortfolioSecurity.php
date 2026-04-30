<?php

namespace App\Filament\Resources\PortfolioSecurities\Pages;

use App\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource;
use App\Filament\Resources\Securities\Pages\EditSecurity;
use Illuminate\Contracts\Support\Htmlable;

class EditPortfolioSecurity extends EditSecurity
{
    protected static string $resource = PortfolioSecurityResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name ?? $this->record->ticker ?? $this->record->isin;
    }
}
