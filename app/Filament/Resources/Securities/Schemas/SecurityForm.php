<?php

namespace App\Filament\Resources\Securities\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
            ]);
    }
}
