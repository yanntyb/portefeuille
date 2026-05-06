<?php

namespace App\Domains\Portfolio\Filament\Resources\Transactions;

use App\Domains\Portfolio\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Domains\Portfolio\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Domains\Portfolio\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Domains\Portfolio\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Domains\Portfolio\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Domains\Portfolio\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'transaction';

    protected static ?string $pluralModelLabel = 'transactions';

    protected static ?string $navigationLabel = 'Transactions';

    protected static string|UnitEnum|null $navigationGroup = 'Portefeuille';

    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
