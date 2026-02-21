<?php

namespace App\Filament\Resources\Securities\Schemas;

use App\Models\Security;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class SecurityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('isin')
                    ->label('ISIN')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(12)
                    ->placeholder('FR0011871110'),
                TextInput::make('name')
                    ->label('Nom')
                    ->maxLength(255)
                    ->placeholder('Nom du titre'),
                TextInput::make('ticker')
                    ->label('Ticker'),
                TextEntry::make('total_invested')
                    ->label('Total investi')
                    ->state(function (Security $record): string {
                        $total = $record->transactions()->selectRaw('SUM(quantity * unit_price) + SUM(fees) as total')->value('total');

                        return Number::currency($total ?? 0, 'EUR');
                    })
                    ->visibleOn('edit'),
            ]);
    }
}
