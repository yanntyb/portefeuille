<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Datas\ClassAnalysisData;

/**
 * L'analyse d'une exposition entière : la chute encaissée par la poche, et la matrice de
 * corrélations de ses lignes. Un port à part et non une méthode de `PortfolioOverviewPort` : cette
 * lecture croise le marché et le portefeuille, elle n'est pas un aperçu chiffré de plus.
 */
interface ClassAnalysisPort
{
    /** Vide quand l'exposition ne porte rien : sans ligne, il n'y a rien à corréler. */
    public function forClass(int $userId, AssetClass $exposure): ClassAnalysisData;
}
