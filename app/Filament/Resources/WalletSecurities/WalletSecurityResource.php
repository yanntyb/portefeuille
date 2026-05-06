<?php

namespace App\Filament\Resources\WalletSecurities;

use App\Domains\Security\Filament\Resources\SecurityBase\RelationManagers\TransactionsRelationManager;
use App\Domains\Security\Filament\Resources\SecurityBase\Schemas\SecurityForm;
use App\Domains\Security\Models\Security;
use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

class WalletSecurityResource extends Resource
{
    protected static ?string $model = Security::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'titres';

    public static function form(Schema $schema): Schema
    {
        return SecurityForm::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditWalletSecurity::route('/{record}/edit'),
        ];
    }
}
