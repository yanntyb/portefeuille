<?php

namespace App\Contexts\Identity;

use Illuminate\Support\ServiceProvider;

class IdentityProvider extends ServiceProvider
{
    public static function registers(): void
    {
        // Pas de binding pour l'instant (User/Role sans contrat).
    }
}
