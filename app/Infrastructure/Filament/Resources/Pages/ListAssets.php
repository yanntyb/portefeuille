<?php

namespace App\Infrastructure\Filament\Resources\Pages;

use App\Infrastructure\Filament\Resources\AssetResource;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;
}
