<?php

namespace App\Extensions;

use Filament\Contracts\Plugin;
use Filament\Panel;

abstract class Extension implements Plugin
{
    public static function make(): static
    {
        app()->singletonIf(static::class);

        return app(static::class);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
