<?php

namespace App\Domains\User\Filament\Resources\Users\Pages;

use App\Domains\User\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
