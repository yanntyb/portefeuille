<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('generateInvitation')
                ->label('Générer une invitation')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->modalHeading("Générer un lien d'invitation")
                ->modalDescription('Un lien valable 7 jours sera généré. Partagez-le avec la personne concernée.')
                ->modalSubmitActionLabel('Générer')
                ->visible(fn () => auth()->user()->isAdmin())
                ->action(function () {
                    $invitation = Invitation::create([
                        'token' => Str::uuid()->toString(),
                        'created_by' => auth()->id(),
                        'expires_at' => now()->addDays(7),
                    ]);

                    $url = route('invitation.register', ['token' => $invitation->token]);

                    Notification::make()
                        ->title("Lien d'invitation généré")
                        ->body($url)
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
