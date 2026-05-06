<?php

namespace App\Domains\User\Filament\Resources\Users\Pages;

use App\Domains\User\Filament\Resources\Users\UserResource;
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
                ->visible(fn (): bool => app()->isLocal()),
            DeleteAction::make(),
        ];
    }
}
