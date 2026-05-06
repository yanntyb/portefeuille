<?php

namespace App\Domains\User\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum Role: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case User = 'user';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'Utilisateur',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::User => 'info',
        };
    }
}
