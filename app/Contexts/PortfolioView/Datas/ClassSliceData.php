<?php

namespace App\Contexts\PortfolioView\Datas;

use JsonSerializable;

/**
 * Une classe d'actif dans un périmètre plus large — une enveloppe, aujourd'hui : ce qu'elle y
 * vaut, et la part qu'elle y pèse.
 *
 * Le libellé arrive rendu et la part déjà calculée : le front ne regroupe ni ne divise rien, c'est
 * la règle du dépôt — un pourcentage calculé côté écran divergerait de celui du serveur au premier
 * arrondi.
 */
readonly class ClassSliceData implements JsonSerializable
{
    /** @param  float  $share  part du périmètre, en pourcentage */
    public function __construct(
        public string $key,
        public string $label,
        public float $value,
        public float $share,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'share' => $this->share,
        ];
    }
}
