<?php

namespace App\Filament\Resources\Invitations;

use App\Filament\Resources\Invitations\Pages\ListInvitations;
use App\Models\Invitation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'invitation';

    protected static ?string $pluralModelLabel = 'invitations';

    protected static ?string $navigationLabel = 'Invitations';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('createdBy.name')
                    ->label('Créé par')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('used_at')
                    ->label('Utilisée le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->state(fn (Invitation $record): string => $record->isUsed() ? 'Utilisée' : ($record->isExpired() ? 'Expirée' : 'Valide'))
                    ->color(fn (string $state): string => match ($state) {
                        'Valide' => 'success',
                        'Expirée' => 'warning',
                        'Utilisée' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('copyLink')
                    ->label('Copier le lien')
                    ->icon('heroicon-o-clipboard-document')
                    ->iconButton()
                    ->tooltip('Copier le lien d\'invitation')
                    ->visible(fn (Invitation $record): bool => $record->isValid())
                    ->extraAttributes(fn (Invitation $record): array => [
                        'x-data' => '',
                        '@click.prevent' => "navigator.clipboard.writeText('".route('invitation.register', ['token' => $record->token])."')",
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvitations::route('/'),
        ];
    }
}
