<?php

use App\Filament\Pages\Auth\Login;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('logs in as demo user and creates demo data', function () {
    auth()->logout();

    livewire(Login::class)
        ->call('loginAsDemo')
        ->assertRedirect();

    $user = User::where('email', 'demo@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->wallets)->not->toBeEmpty();
});

it('does not re-seed when demo user already has data', function () {
    auth()->logout();

    livewire(Login::class)
        ->call('loginAsDemo')
        ->assertRedirect();

    $walletCount = User::where('email', 'demo@example.com')->first()->wallets()->count();

    auth()->logout();

    livewire(Login::class)
        ->call('loginAsDemo')
        ->assertRedirect();

    expect(User::where('email', 'demo@example.com')->first()->wallets()->count())
        ->toBe($walletCount);
});

it('shows the demo button on the login page', function () {
    auth()->logout();

    $this->get('/login')
        ->assertSeeText('Présentation');
});
