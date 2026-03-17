<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Facades\Impersonation;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->iconButton()
                ->icon('impersonate-icon')
                ->visible(fn (): bool => auth()->user()->canImpersonate()
                    && ! Impersonation::isImpersonating()
                    && ! $this->record->is(auth()->user()))
                ->action(function (): void {
                    $guard = Filament::getCurrentOrDefaultPanel()->getAuthGuard();

                    session()->put([
                        'impersonate.back_to' => url()->previous(),
                        'impersonate.guard' => $guard,
                    ]);

                    Impersonation::enter(auth()->user(), $this->record, $guard);

                    $this->redirect('/', navigate: false);
                }),
            DeleteAction::make(),
        ];
    }
}
