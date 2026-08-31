<?php

use App\Contexts\Identity\Http\AuthenticateDefaultUser;
use App\Contexts\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Le middleware seul, sans passer par une route : on lit ce que `auth()` porte au terme du passage. */
function passThrough(): ?User
{
    $seen = null;

    app(AuthenticateDefaultUser::class)->handle(
        Request::create('/'),
        function () use (&$seen): Response {
            $seen = auth()->user();

            return new Response;
        },
    );

    return $seen;
}

it('connects the only user in the database', function () {
    $user = User::factory()->create();

    expect(passThrough()?->id)->toBe($user->id);
});

it('connects the first user when several exist', function () {
    $first = User::factory()->create();
    User::factory()->create();

    /** Ordre explicite : `first()` sans `orderBy` dépend du moteur. */
    expect(passThrough()?->id)->toBe($first->id);
});

it('leaves nobody connected on an empty database, and creates nobody', function () {
    /**
     * Une base fraîchement migrée n'a aucun utilisateur — `MigrationsLeaveNoUserTest` le fige — et
     * l'accueil doit rester lisible sur une installation vierge. Le middleware ne comble donc pas
     * le vide, il le laisse voir.
     */
    expect(passThrough())->toBeNull()
        ->and(User::query()->count())->toBe(0);
});

it('leaves an already connected user in place', function () {
    User::factory()->create();
    $chosen = User::factory()->create();

    auth()->setUser($chosen);

    expect(passThrough()?->id)->toBe($chosen->id);
});
