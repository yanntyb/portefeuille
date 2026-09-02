<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\BasketAnalysisData;

/**
 * L'analyse d'un panier de positions — une exposition entière, une enveloppe : la chute encaissée
 * par le panier, et la matrice de corrélations de ses lignes. Le périmètre dit lesquelles ; la
 * lecture, elle, est la même, et c'est pourquoi une seule composition la sert.
 *
 * Un port à part et non une méthode de `PortfolioOverviewPort` : cette lecture croise le marché et
 * le portefeuille, elle n'est pas un aperçu chiffré de plus.
 */
interface BasketAnalysisPort
{
    /** Vide quand le périmètre ne porte rien : sans ligne, il n'y a rien à corréler. */
    public function analysisFor(int $userId, HoldingScope $scope): BasketAnalysisData;
}
