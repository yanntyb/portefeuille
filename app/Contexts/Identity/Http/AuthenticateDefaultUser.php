<?php

namespace App\Contexts\Identity\Http;

use App\Contexts\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification maison, sans écran de connexion : l'utilisateur par défaut est connecté à la
 * volée. Le seul but est que `auth()->user()` soit fiable partout, pour que le repli
 * `?? User::query()->first()` disparaisse des contrôleurs — il y était recopié six fois, et une
 * route d'écriture ne peut pas s'en contenter.
 *
 * `setUser()` et non `login()` : aucune écriture de session, aucun évènement `Login`, aucune
 * régénération d'identifiant de session. Idempotent, donc valable aussi sur `/instantane`, que le
 * service worker rappelle en arrière-plan.
 *
 * Le jour où un formulaire de connexion arrive, c'est le seul fichier à reprendre.
 */
class AuthenticateDefaultUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            /**
             * Aucun utilisateur en base : on ne fait rien et on n'en crée aucun.
             * `tests/Feature/MigrationsLeaveNoUserTest.php` fige qu'une base fraîchement migrée
             * n'en porte pas, et l'accueil doit rester lisible sur une installation vierge — les
             * contrôleurs de lecture gardent leurs `Data::empty()` pour ce cas.
             *
             * `orderBy('id')` : l'ordre explicite rend le choix déterministe d'un moteur à l'autre.
             */
            $user = User::query()->orderBy('id')->first();

            if ($user !== null) {
                auth()->setUser($user);
            }
        }

        return $next($request);
    }
}
