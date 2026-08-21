<?php

namespace App\Contexts\RealEstate\Ports;

use Closure;

interface RealEstateCachePort
{
    /**
     * Rend la série déjà calculée pour cet utilisateur, ou la calcule et la retient.
     *
     * L'implémentation décide seule quand un résultat est périmé : les actions n'ont ni clé ni
     * durée à fournir.
     */
    public function remember(string $name, int $userId, Closure $callback): mixed;
}
