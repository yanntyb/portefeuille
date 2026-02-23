<?php

namespace App\Filament\Resources\Securities\Pages;

use App\Filament\Resources\Securities\Schemas\SecurityForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

abstract class EditSecurity extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            SecurityForm::updateFromIsinAction(),
            DeleteAction::make(),
        ];
    }
}
