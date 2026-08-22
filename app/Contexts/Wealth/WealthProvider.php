<?php

namespace App\Contexts\Wealth;

use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Ports\AssetClassPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WealthProvider extends ServiceProvider
{
    /**
     * L'ordre des classes est un contrat : il fixe l'ordre des lignes du résumé, l'empilement des
     * bandes du graphe et l'affectation des couleurs.
     *
     * @param  list<class-string<AssetClassPort>>  $classes  classes d'actif à agréger, dans l'ordre
     */
    public static function registers(Application $app, array $classes): void
    {
        $app->tag($classes, 'wealth.classes');

        /**
         * Les trois actions du contexte (résumé, série, revenus) partagent le registre pour la
         * durée de la requête : `scoped()` lui donne un unique cycle de vie par requête plutôt
         * que trois. Les lectures sous-jacentes ne sont pas mémoïsées pour autant.
         */
        $app->scoped(
            AssetClassRegistry::class,
            fn (Application $app): AssetClassRegistry => new AssetClassRegistry($app->tagged('wealth.classes')),
        );
    }
}
