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
 * 3. `investi(Liquidités) = ce qui reste du reliquat` — le capital rendu par une vente et pas
 *    replacé revient à la caisse, où il dort. Sans plancher : des apports nets négatifs — avoir
 *    repris plus qu'on n'a mis — donnent un investi négatif, qui est le sens de la situation.
 *
 * Le deuxième temps n'est pas un raffinement : sans lui, arbitrer une exposition contre une autre
 * fait disparaître l'apport du total. `CashLedger::netContributions()` est délibérément collant —
 * un achat de crypto payé par le produit d'une vente d'actions n'impute aucun apport à la crypto,
 * l'étiquette reste sur les actions. Sur `apport 1 000 → achat actions → vente 1 200 → achat crypto
 * 1 200`, le premier temps seul donne `min(1 000, 0) = 0` aux actions et `min(0, 1 200) = 0` à la
 * crypto : 1 000 € d'apports nets s'évaporaient. La redistribution les rend à l'exposition qui
 * porte désormais le capital.
 *
 * Le troisième temps n'a **pas** de borne haute au solde. Elle a existé, pour un cas précis : un
 * apport encore immobilisé en titres aurait affiché un gain négatif sur une caisse vide. C'est
 * exactement ce dont le deuxième temps se charge désormais — le capital engagé est imputé à
 * l'exposition qui le porte avant que rien ne tombe aux liquidités —, et ce qui reste après lui
 * n'est plus du capital immobilisé : c'est de l'apport que le marché a consommé. La borne ne
 * protégeait donc plus que d'une chose, faire disparaître une moins-value réalisée de l'écran :
 * `apport 1 000 → achat 1 000 → vente 600` annonçait « Investi 600, Gain 0 € » pour 1 000 € sortis
 * de la poche et 600 € en caisse. Sans elle, les liquidités portent « Investi 1 000, Gain −400 € »,
 * la perte réellement encaissée, affichée là où l'argent se trouve.
 *
 * Ce n'est pas un double comptage avec le `realizedGain` de l'exposition : `GetWealthOverview`
 * calcule `totalGain = totalValue − totalInvested` et porte le réalisé dans un champ séparé, qu'il
 * n'additionne jamais. Le cas gagnant, ratifié depuis la tâche 10, est le miroir exact du cas
 * perdant — une vente à 1 200 € met déjà `+200` en réalisé sur l'exposition ET `+200` en gain sur
 * les liquidités.
 *
 * Règle d'or que garde `WealthInvariantTest`, désormais sans aucune exception : la somme des
 * investis de toutes les classes fait exactement les apports nets. Elle est vraie par construction
 * — `Σ min(imputé, coût) ≤ Σ imputé ≤ apports nets`, et la redistribution ne dépasse jamais le
 * reliquat, si bien que la part des liquidités le solde exactement.
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
    public function allocate(array $imputedContributions, array $costOfHoldings, float $netContributions): array
    {
        $exposures = [];

        foreach ($costOfHoldings as $key => $cost) {
            $exposures[$key] = $this->forExposure($imputedContributions[$key] ?? 0.0, $cost);
        }

        $exposures = $this->replace($exposures, $costOfHoldings, round($netContributions - $this->sumOf($exposures), 2));

        return [
            'exposures' => $exposures,
            'cash' => $this->forCash($netContributions, $this->sumOf($exposures)),
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
     * Ni borne haute ni plancher — voir l'en-tête de la classe. Pas de borne haute au solde : la
     * caisse peut porter plus de capital qu'elle ne détient d'euros, et c'est ainsi qu'une
     * moins-value réalisée s'affiche au lieu de s'évaporer. Pas de plancher à zéro non plus :
     * retirer le produit d'une vente rend les apports nets négatifs — le porteur a repris plus
     * qu'il n'a mis —, et un investi de −200 € pour une caisse vide dit un gain de +200 €, ce qui
     * est exact. Masquer ce signe annonçait « −1 000 € » sur un porteur en réalité gagnant.
     *
     * Sans borne d'aucun côté, l'invariant devient vrai par pure construction : la part des
     * liquidités solde exactement ce que les expositions n'immobilisent pas.
     */
    public function forCash(float $netContributions, float $exposuresInvested): float
    {
        return round($netContributions - $exposuresInvested, 2);
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

    /**
     * La même part, pour la série du tableau de bord — et celle-là garde sa borne au solde.
     *
     * Deux raisons, toutes deux propres à la série. Elle ne redistribue rien : son coût de revient
     * est un total, pas une répartition par exposition, donc le deuxième temps de la règle n'y a
     * pas lieu et ne peut pas protéger le capital encore immobilisé. Et ce coût vient de
     * `BuildEvolutionSeries`, piloté par les cours : il ne pose aucun point avant le premier cours
     * connu, ni pour un actif qui n'en a aucun. Sans la borne, chacune de ces dates afficherait
     * l'apport entier face à une caisse vide — un creux de plusieurs milliers d'euros sur la bande,
     * pour un trou de données, pas pour une perte.
     *
     * Conséquence assumée : la bande sous-estime une moins-value réalisée, là où l'instantané la
     * dit. C'est l'instantané qui fait foi ; la série reste une approximation, comme son coût.
     */
    public function forCashSeries(float $netContributions, float $costOfHoldings, float $balance): float
    {
        return round(min($this->forCash($netContributions, $costOfHoldings), $balance), 2);
    }

    /** @param  array<string, float>  $amounts */
    private function sumOf(array $amounts): float
    {
        return round(array_sum($amounts), 2);
    }
}
