<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make()
                ->iconButton()
                ->redirectTo('/')
                ->action(function (Impersonate $action): void {
                    $action->impersonate($this->record);
                    $action->redirect('/', navigate: false);
                }),
            DeleteAction::make(),
        ];
    }
}
