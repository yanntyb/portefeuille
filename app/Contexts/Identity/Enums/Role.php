<?php

namespace App\Contexts\Identity\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'Utilisateur',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::User => 'info',
        };
    }
}
