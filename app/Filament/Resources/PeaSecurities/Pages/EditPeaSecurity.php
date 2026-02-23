<?php

namespace App\Filament\Resources\PeaSecurities\Pages;

use App\Filament\Resources\PeaSecurities\PeaSecurityResource;
use App\Filament\Resources\Securities\Schemas\SecurityForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPeaSecurity extends EditRecord
{
    protected static string $resource = PeaSecurityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SecurityForm::updateFromIsinAction(),
            DeleteAction::make(),
        ];
    }
}
