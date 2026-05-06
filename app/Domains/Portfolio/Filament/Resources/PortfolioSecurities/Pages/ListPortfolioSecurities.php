<?php

namespace App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\Pages;

use App\Domains\Portfolio\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortfolioSecurities extends ListRecords
{
    protected static string $resource = PortfolioSecurityResource::class;

    protected static ?string $title = 'Titres';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
