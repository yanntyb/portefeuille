<?php

namespace App\Filament\Pages\Auth;

use Database\Seeders\DemoSeeder;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function loginAsDemo(): void
    {
        $user = app(DemoSeeder::class)->run();

        Auth::login($user);

        $this->redirect(filament()->getUrl());
    }
}
