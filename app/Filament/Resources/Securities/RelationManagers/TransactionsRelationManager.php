<?php

namespace App\Filament\Resources\Securities\RelationManagers;

use App\Enums\AccountType;
use App\Models\SecurityPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_type')
                    ->label('Type de compte')
                    ->options(AccountType::class)
                    ->required()
                    ->live(),

                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->default(now()),

                TextInput::make('broker')
                    ->label('Courtier')
                    ->hiddenJs(<<<'JS'
                        $get('account_type') !== 'cto'
                        JS),

                TextInput::make('quantity')
                    ->label('Quantité')
                    ->numeric()
                    ->minValue(0),

                TextInput::make('unit_price')
                    ->label('Prix unitaire')
                    ->numeric()
                    ->prefix('€')
                    ->minValue(0)
                    ->default(function (RelationManager $livewire): ?float {
                        $price = SecurityPrice::query()
                            ->where('security_id', $livewire->getOwnerRecord()->id)
                            ->orderByDesc('date')
                            ->value('close');

                        return $price !== null ? (float) $price : null;
                    }),

                TextInput::make('fees')
                    ->label('Frais')
                    ->numeric()
                    ->prefix('€')
                    ->default(0)
                    ->minValue(0),

                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('user_id', auth()->id()))
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->isoDate('MMM YYYY')
                    ->sortable(),
                TextColumn::make('account_type')
                    ->label('Compte')
                    ->badge()
                    ->sortable(),
                TextColumn::make('broker')
                    ->label('Courtier')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(decimalPlaces: 4)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label('Prix unitaire')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('fees')
                    ->label('Frais')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('account_type')
                    ->label('Compte')
                    ->options(AccountType::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
