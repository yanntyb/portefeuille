<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;

/**
 * Une classe d'actif du patrimoine. Elle se décrit — sa clé, son libellé, la page qui la détaille —
 * et se mesure — ce qu'elle vaut aujourd'hui, ce qu'elle valait, ce qu'elle rapporte chaque mois.
 *
 * Le contexte Wealth ne connaît que cette interface : ajouter une classe, c'est écrire un
 * adaptateur et l'ajouter au registre, jamais toucher aux actions.
 */
interface AssetClassPort
{
    /** Identifiant stable, celui que le front lit dans le JSON. */
    public function key(): string;

    public function label(): string;

    /**
     * La page qui détaille la classe, vers laquelle le tableau de bord renvoie, et `null` pour une
     * classe qui n'en a pas : sa ligne se lit alors sans être cliquable.
     */
    public function href(): ?string;

    /**
     * Jeton de teinte de la classe, résolu par thème côté front. Porté par la classe et non déduit
     * de son rang : un rang décidait autrefois de la couleur, et la quatrième classe reprenait
     * celle de la première.
     */
    public function color(): string;

    public function snapshotFor(int $userId): ClassSnapshotData;

    /**
     * À quels secteurs la classe expose son porteur, et pour combien. Un portefeuille les tient de
     * ses titres ; une classe qui n'a rien de sectoriel — un parc immobilier — rend une tranche
     * unique portant son nom, plutôt que de se dérober au partage.
     *
     * @return list<ClassSectorData>
     */
    public function sectorSlicesFor(int $userId): array;

    public function seriesFor(int $userId): ClassSeriesData;

    /**
     * Libellé du revenu mensuel — « Dividendes », « Locatif net » —, ou `null` quand la classe
     * n'en produit aucun. La crypto ne verse rien : une ligne à zéro n'apprendrait rien.
     */
    public function incomeLabel(): ?string;

    public function monthlyIncomeFor(int $userId): float;
}
