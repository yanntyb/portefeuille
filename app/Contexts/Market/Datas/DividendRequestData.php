<?php

namespace App\Contexts\Market\Datas;

/**
 * Demande de détachements pour un ticker sur une fenêtre de dates inclusive.
 *
 * Traduire la fenêtre dans la convention d'un fournisseur appartient à l'adaptateur.
 */
readonly class DividendRequestData
{
    public function __construct(
        public string $ticker,
        public string $startDate,
        public string $endDate,
    ) {}
}
