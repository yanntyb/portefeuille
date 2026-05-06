<?php

namespace App\Domains\Portfolio\Filament\Resources\Transactions\Pages;

use App\Domains\Portfolio\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected Width|string|null $maxContentWidth = Width::ScreenExtraLarge;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
