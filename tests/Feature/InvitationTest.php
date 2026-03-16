<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Livewire\InvitationRegistration;
use App\Models\Invitation;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it("l'action de génération d'invitation est visible pour un admin", function () {
    actingAs(User::factory()->admin()->create());

    livewire(ListUsers::class)
        ->assertActionVisible('generateInvitation');
});

it('génère une invitation et la stocke en base', function () {
    actingAs(User::factory()->admin()->create());

    livewire(ListUsers::class)
        ->callAction('generateInvitation')
        ->assertNotified();

    expect(Invitation::count())->toBe(1);
    expect(Invitation::first()->token)->not->toBeEmpty();
    expect(Invitation::first()->expires_at)->not->toBeNull();
});

it('stocke un token unique en base', function () {
    actingAs(User::factory()->admin()->create());

    livewire(ListUsers::class)->callAction('generateInvitation');
    livewire(ListUsers::class)->callAction('generateInvitation');

    expect(Invitation::count())->toBe(2);
    expect(Invitation::pluck('token')->unique()->count())->toBe(2);
});

it('affiche le formulaire pour un token valide', function () {
    $invitation = Invitation::factory()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->assertOk()
        ->assertSet('tokenInvalid', false);
});

it('retourne 404 pour un token inexistant', function () {
    livewire(InvitationRegistration::class, ['token' => 'token-inexistant'])
        ->assertStatus(404);
});

it('indique une erreur pour une invitation expirée', function () {
    $invitation = Invitation::factory()->expired()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->assertOk()
        ->assertSet('tokenInvalid', true);
});

it('indique une erreur pour une invitation déjà utilisée', function () {
    $invitation = Invitation::factory()->used()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->assertOk()
        ->assertSet('tokenInvalid', true);
});

it('inscrit un utilisateur avec des données valides', function () {
    $invitation = Invitation::factory()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->set('name', 'Jean Dupont')
        ->set('email', 'jean@exemple.com')
        ->set('password', 'motdepasse123')
        ->set('password_confirmation', 'motdepasse123')
        ->call('submit')
        ->assertHasNoErrors();

    assertDatabaseHas(User::class, [
        'name' => 'Jean Dupont',
        'email' => 'jean@exemple.com',
    ]);
});

it("marque l'invitation comme utilisée après inscription", function () {
    $invitation = Invitation::factory()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->set('name', 'Jean Dupont')
        ->set('email', 'jean@exemple.com')
        ->set('password', 'motdepasse123')
        ->set('password_confirmation', 'motdepasse123')
        ->call('submit');

    expect($invitation->fresh()->isUsed())->toBeTrue();
});

it('redirige vers /admin après inscription', function () {
    $invitation = Invitation::factory()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->set('name', 'Jean Dupont')
        ->set('email', 'jean@exemple.com')
        ->set('password', 'motdepasse123')
        ->set('password_confirmation', 'motdepasse123')
        ->call('submit')
        ->assertRedirect('/admin');
});

it('ne peut pas réutiliser une invitation déjà utilisée', function () {
    $invitation = Invitation::factory()->used()->create();

    livewire(InvitationRegistration::class, ['token' => $invitation->token])
        ->set('name', 'Jean Dupont')
        ->set('email', 'jean@exemple.com')
        ->set('password', 'motdepasse123')
        ->set('password_confirmation', 'motdepasse123')
        ->call('submit');

    // L'utilisateur ne doit pas être créé car le token est invalide
    expect(User::where('email', 'jean@exemple.com')->exists())->toBeFalse();
});
