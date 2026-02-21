<?php

namespace App\Filament\Resources\CtoSecurities\Pages;

use App\Filament\Resources\CtoSecurities\CtoSecurityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCtoSecurity extends EditRecord
{
    protected static string $resource = CtoSecurityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
