<?php

namespace App\Filament\Resources\PortfolioSecurities\Pages;

use App\Filament\Resources\PortfolioSecurities\PortfolioSecurityResource;
use Filament\Resources\Pages\ListRecords;

class ListPortfolioSecurities extends ListRecords
{
    protected static string $resource = PortfolioSecurityResource::class;

    protected static ?string $title = 'Titres';

    /** @var list<int> */
    public array $shownSecurityIds = [];

    /** @var list<int> */
    public array $pricelessSecurityIds = [];
}
