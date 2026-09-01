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
 * La règle tient en trois temps, et l'apport y suit l'argent :
 *
 * 1. `investi(exposition) = min(apports imputés à cette exposition, coût de revient des titres détenus)`
 *    — l'apport reste imputé à l'exposition tant qu'il y est immobilisé, pas au-delà ;
 * 2. le reliquat — `apports nets − somme des investis` — se redistribue aux expositions dont le
 *    coût n'est pas encore financé, à hauteur de ce qui leur manque ;
 * 3. `investi(Liquidités) = borne(ce qui reste du reliquat, 0, solde)` — le capital rendu par une
 *    vente et pas replacé revient à la caisse, où il dort.
 *
 * Le deuxième temps n'est pas un raffinement : sans lui, arbitrer une exposition contre une autre
 * fait disparaître l'apport du total. `CashLedger::netContributions()` est délibérément collant —
 * un achat de crypto payé par le produit d'une vente d'actions n'impute aucun apport à la crypto,
 * l'étiquette reste sur les actions. Sur `apport 1 000 → achat actions → vente 1 200 → achat crypto
 * 1 200`, le premier temps seul donne `min(1 000, 0) = 0` aux actions et `min(0, 1 200) = 0` à la
 * crypto : 1 000 € d'apports nets s'évaporaient. La redistribution les rend à l'exposition qui
 * porte désormais le capital.
 *
 * Règle d'or que garde `WealthInvariantTest` : la somme des investis de toutes les classes fait
 * exactement les apports nets.
 *
 * Service pur, sans Eloquent ni port : `PortfolioAssetClass` et `PortfolioCash` le consomment
 * plutôt que de porter chacun sa formule — `PortfolioCash` la portait, et `Infrastructure/` n'est
 * pas le lieu d'une règle métier.
 */
class InvestedCapital
{
    /**
     * La répartition complète : ce que chaque exposition immobilise d'apport, et ce qui reste aux
     * liquidités. Une exposition a besoin de la photo globale pour connaître sa part — le reliquat
     * d'une classe se replace dans une autre — et c'est le prix d'un total juste.
     *
     * @param  array<string, float>  $imputedContributions  apports imputés, par exposition
     * @param  array<string, float>  $costOfHoldings  coût de revient des titres détenus, par exposition
     * @return array{exposures: array<string, float>, cash: float}
     */
    public function allocate(array $imputedContributions, array $costOfHoldings, float $netContributions, float $cash): array
    {
        $exposures = [];

        foreach ($costOfHoldings as $key => $cost) {
            $exposures[$key] = $this->forExposure($imputedContributions[$key] ?? 0.0, $cost);
        }

        $exposures = $this->replace($exposures, $costOfHoldings, round($netContributions - $this->sumOf($exposures), 2));

        return [
            'exposures' => $exposures,
            'cash' => $this->forCash($netContributions, $this->sumOf($exposures), $cash),
        ];
    }

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

    /**
     * Rend le reliquat aux expositions dont le coût n'est pas encore financé, au prorata de ce qui
     * manque à chacune. Au prorata et non au premier servi : deux expositions arbitrées le même
     * jour n'ont aucune raison que l'ordre du registre décide laquelle porte l'apport.
     *
     * Ce qui excède le manque total reste au reliquat et tombera aux liquidités : le capital qui
     * n'a été replacé nulle part dort en caisse. La dernière part se calcule par différence, pour
     * que les arrondis au centime ne perdent jamais un euro du total.
     *
     * @param  array<string, float>  $exposures
     * @param  array<string, float>  $costOfHoldings
     * @return array<string, float>
     */
    private function replace(array $exposures, array $costOfHoldings, float $leftover): array
    {
        if ($leftover <= 0.0) {
            return $exposures;
        }

        $missing = [];

        foreach ($exposures as $key => $invested) {
            $gap = round(($costOfHoldings[$key] ?? 0.0) - $invested, 2);

            if ($gap > 0.0) {
                $missing[$key] = $gap;
            }
        }

        if ($missing === []) {
            return $exposures;
        }

        $totalMissing = $this->sumOf($missing);
        $granted = min($leftover, $totalMissing);
        $assigned = 0.0;
        $keys = array_keys($missing);
        $last = count($keys) - 1;

        foreach ($keys as $index => $key) {
            $share = $index === $last
                ? round($granted - $assigned, 2)
                : round($granted * $missing[$key] / $totalMissing, 2);

            $exposures[$key] = round($exposures[$key] + $share, 2);
            $assigned = round($assigned + $share, 2);
        }

        return $exposures;
    }

    /** @param  array<string, float>  $amounts */
    private function sumOf(array $amounts): float
    {
        return round(array_sum($amounts), 2);
    }
}
