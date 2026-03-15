<?php

namespace App\Extensions;

use Filament\Panel;
use Filament\Tables\Table;

class TablePersistence extends Extension
{
    public function getId(): string
    {
        return 'table-persistence';
    }

    public function register(Panel $panel): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession());
    }
}
