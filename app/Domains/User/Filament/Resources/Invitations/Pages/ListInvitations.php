<?php

namespace App\Domains\User\Filament\Resources\Invitations\Pages;

use App\Domains\User\Filament\Resources\Invitations\InvitationResource;
use App\Domains\User\Models\Invitation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateInvitation')
                ->label('Générer une invitation')
                ->icon('heroicon-o-link')
                ->requiresConfirmation()
                ->modalHeading("Générer un lien d'invitation")
                ->modalDescription('Un lien valable 7 jours sera généré. Partagez-le avec la personne concernée.')
                ->modalSubmitActionLabel('Générer')
                ->action(function (): void {
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
