<?php

namespace App\Contexts\Wealth\Services;

/**
 * Comment les apports nets se répartissent entre les classes de patrimoine.
 *
 * « Investi » ne veut plus dire « coût de revient des titres détenus » depuis le chantier des
 * liquidités : c'est ce que le porteur a réellement sorti de sa poche. Déclarer `totalCost` sur une
 * exposition recompterait donc l'apport à chaque aller-retour — apport 1 000, achat 1 000, vente
 * 1 200, rachat 1 200 affichait « Investi 1 200, Gain 0 € » là où la vérité est « Investi 1 000,
 * Gain +200 € ».
 *
 * La règle tient en deux lignes, et l'apport y suit l'argent :
 *
 * - `investi(exposition) = min(apports imputés à cette exposition, coût de revient des titres détenus)`
 *   — l'apport reste imputé à l'exposition tant qu'il y est immobilisé, pas au-delà ;
 * - `investi(Liquidités) = borne(apports nets totaux − somme des investis d'exposition, 0, solde)`
 *   — le capital rendu par une vente revient à la caisse, où il dort en attendant d'être replacé.
 *
 * Service pur, sans Eloquent ni port : `PortfolioAssetClass` et `PortfolioCash` le consomment
 * plutôt que de porter chacun sa formule — `PortfolioCash` la portait, et `Infrastructure/` n'est
 * pas le lieu d'une règle métier.
 */
class InvestedCapital
{
    /**
     * Ce qu'une exposition immobilise réellement d'apport : l'imputation d'origine, plafonnée au
     * coût des titres qu'elle détient encore. Sans le plafond, une exposition entièrement soldée
     * garderait son apport et afficherait un gain négatif du montant de sa plus-value.
     */
    public function forExposure(float $imputedContributions, float $costOfHoldings): float
    {
        return round(min($imputedContributions, $costOfHoldings), 2);
    }

    /**
     * Ce que les liquidités portent d'apport : tout ce que les expositions n'immobilisent plus.
     *
     * Borné à `[0, solde]` : un apport encore entièrement en titres ne doit pas rendre l'investi
     * négatif sur une caisse vide, et la caisse ne peut pas porter plus de capital qu'elle ne
     * détient d'euros — le surplus est de la plus-value, pas de l'apport.
     */
    public function forCash(float $netContributions, float $exposuresInvested, float $cash): float
    {
        return round(min(max($netContributions - $exposuresInvested, 0.0), $cash), 2);
    }
}
