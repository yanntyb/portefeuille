<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Wealth\Ports\AssetClassPort;

/**
 * Les classes d'actif du patrimoine, dans l'ordre où elles ont été déclarées.
 *
 * Cet ordre est un contrat, pas un hasard : il fixe l'ordre des lignes du résumé, l'empilement
 * des bandes du graphe et l'affectation des couleurs. Le réordonner change ce que le lecteur voit.
 */
class AssetClassRegistry
{
    /** @param  iterable<AssetClassPort>  $classes */
    public function __construct(private iterable $classes) {}

    /** @return list<AssetClassPort> */
    public function all(): array
    {
        return [...$this->classes];
    }
}
