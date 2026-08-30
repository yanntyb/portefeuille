<?php

namespace App\Contexts\Market\Datas;

/**
 * L'amplitude quotidienne moyenne, en valeur et en pourcentage du dernier cours. Le pourcentage
 * est calculé ici et nulle part ailleurs : c'est lui qu'on lit, parce qu'il se compare d'un
 * instrument à l'autre là où la valeur absolue ne le peut pas.
 *
 * `percent` est nul quand le dernier cours ne vaut rien — il n'y a alors rien à rapporter.
 */
readonly class AtrData
{
    public function __construct(
        public float $value,
        public ?float $percent,
    ) {}
}
