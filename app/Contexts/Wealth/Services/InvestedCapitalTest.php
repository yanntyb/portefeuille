<?php

use App\Contexts\Wealth\Services\InvestedCapital;

it('impute tout l\'apport à l\'exposition tant qu\'il y est immobilisé', function () {
    /** Apport 1 000, achat 1 000 : l'exposition porte les 1 000, la caisse rien. */
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 1000.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 1000.0], 'cash' => 0.0]);
});

it('rend l\'apport aux liquidités dès que les titres sont vendus', function () {
    /** … puis vente 1 200, rien racheté : plus aucun titre à immobiliser l'apport. */
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 0.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0], 'cash' => 1000.0]);
});

it('ne recompte aucun apport quand le produit d\'une vente est réemployé', function () {
    /**
     * … puis rachat 1 200 dans la même exposition : le coût des titres remonte à 1 200, mais
     * l'apport imputé reste 1 000 — l'aller-retour n'a rien sorti de la poche du porteur. C'est le
     * cas que `totalCost` faisait afficher « Investi 1 200, Gain 0 € ».
     */
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 1200.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 1000.0], 'cash' => 0.0]);
});

it('garde à chaque exposition financée séparément le sien, et le reste aux liquidités', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0, 'crypto' => 500.0],
        costOfHoldings: ['equity' => 1000.0, 'crypto' => 500.0],
        netContributions: 1800.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 1000.0, 'crypto' => 500.0], 'cash' => 300.0]);
});

/**
 * L'arbitrage d'une exposition contre une autre. `CashLedger::netContributions()` est collant :
 * l'achat de crypto payé par le produit de la vente d'actions n'impute aucun apport à la crypto,
 * l'étiquette reste sur les actions. Le seul plafonnement au coût donnerait donc `min(1 000, 0)`
 * aux actions et `min(0, 1 200)` à la crypto — 1 000 € d'apports nets évaporés. La redistribution
 * les rend à l'exposition qui porte désormais le capital.
 */
it('déplace l\'apport vers l\'exposition qui a repris le capital', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0, 'crypto' => 0.0],
        costOfHoldings: ['equity' => 0.0, 'crypto' => 1200.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0, 'crypto' => 1000.0], 'cash' => 0.0]);
});

/** Un arbitrage partiel ne déplace que ce qui a été replacé ; le reste dort en caisse. */
it('ne déplace que la part du capital réellement replacée', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0, 'crypto' => 0.0],
        costOfHoldings: ['equity' => 0.0, 'crypto' => 600.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0, 'crypto' => 600.0], 'cash' => 400.0]);
});

/**
 * La moins-value réalisée. La caisse porte plus de capital qu'elle ne détient d'euros, et c'est
 * ainsi que la perte s'affiche : « Investi 1 000, Gain −400 € » pour 600 € en caisse. La borne au
 * solde annonçait « Investi 600, Gain 0 € » — 400 € apportés et perdus s'évaporaient de l'écran.
 */
it('porte aux liquidités la moins-value réellement encaissée', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 0.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0], 'cash' => 1000.0]);
});

/** Une moins-value suivie d'un rachat partiel : la redistribution et la perte se croisent. */
it('replace ce qui est racheté et laisse la perte à la caisse', function () {
    /**
     * Apport 1 000 → achat 1 000 → vente 600 → rachat 400 en crypto. La crypto reprend 400 € de
     * capital ; les 600 € restants d'apport ne valent plus que 200 € en caisse.
     */
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0, 'crypto' => 0.0],
        costOfHoldings: ['equity' => 0.0, 'crypto' => 400.0],
        netContributions: 1000.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0, 'crypto' => 400.0], 'cash' => 600.0]);
});

/**
 * Le retrait du produit d'une vente : les apports nets deviennent négatifs, et l'investi des
 * liquidités avec eux. Le porteur a mis 1 000 € et repris 1 200 € ; « investi −200, valeur 0 »
 * dit un gain de +200 €. Le plancher à zéro annonçait « investi 0 » — et, combiné au calcul
 * d'alors, « −1 000 € » sur un porteur en réalité gagnant.
 */
it('rend un investi négatif quand le porteur a repris plus qu\'il n\'a mis', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 0.0],
        netContributions: -200.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0], 'cash' => -200.0]);
});

/** Vente partielle puis retrait de son produit : la moitié des titres reste, l'apport net aussi. */
it('garde à l\'exposition ses titres quand seul le produit vendu est retiré', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 1000.0],
        costOfHoldings: ['equity' => 500.0],
        netContributions: 400.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 500.0], 'cash' => -100.0]);
});

/** Deux expositions à financer ensemble se partagent le reliquat au prorata de ce qui leur manque. */
it('partage le reliquat au prorata du manque, sans laisser l\'ordre du registre décider', function () {
    $split = (new InvestedCapital)->allocate(
        imputedContributions: ['equity' => 900.0, 'bond' => 0.0, 'crypto' => 0.0],
        costOfHoldings: ['equity' => 0.0, 'bond' => 300.0, 'crypto' => 600.0],
        netContributions: 900.0,
    );

    expect($split)->toBe(['exposures' => ['equity' => 0.0, 'bond' => 300.0, 'crypto' => 600.0], 'cash' => 0.0]);
});

/**
 * La règle d'or : la somme des investis de toutes les classes fait exactement les apports nets.
 * C'est l'invariant qui a rattrapé l'arbitrage entre expositions ; qu'il ne puisse plus le laisser
 * passer.
 */
it('répartit les apports nets sans en perdre ni en inventer', function (
    array $imputed,
    array $costs,
    float $netContributions,
) {
    $split = (new InvestedCapital)->allocate($imputed, $costs, $netContributions);

    expect(round(array_sum($split['exposures']) + $split['cash'], 2))->toBe($netContributions);

    foreach ($split['exposures'] as $invested) {
        expect($invested)->toBeGreaterThanOrEqual(0.0);
    }
})->with([
    'apport 1 000, achat 1 000' => [['equity' => 1000.0], ['equity' => 1000.0], 1000.0],
    'vente 1 200, rien racheté' => [['equity' => 1000.0], ['equity' => 0.0], 1000.0],
    'rachat 1 200 dans la même exposition' => [['equity' => 1000.0], ['equity' => 1200.0], 1000.0],
    'deux expositions financées séparément' => [['equity' => 1000.0, 'crypto' => 500.0], ['equity' => 1000.0, 'crypto' => 500.0], 1500.0],
    'arbitrage complet vers une autre exposition' => [['equity' => 1000.0, 'crypto' => 0.0], ['equity' => 0.0, 'crypto' => 1200.0], 1000.0],
    'arbitrage partiel, moitié laissée en caisse' => [['equity' => 1000.0, 'crypto' => 0.0], ['equity' => 0.0, 'crypto' => 600.0], 1000.0],
    'vente de la moitié' => [['equity' => 1000.0], ['equity' => 500.0], 1000.0],
    'apport 1 000, retrait 300' => [[], [], 700.0],
    'moins-value réalisée' => [['equity' => 1000.0], ['equity' => 0.0], 1000.0],
    'moins-value puis rachat partiel' => [['equity' => 1000.0, 'crypto' => 0.0], ['equity' => 0.0, 'crypto' => 400.0], 1000.0],
    'moins-value sur une position à moitié soldée' => [['equity' => 1000.0], ['equity' => 500.0], 1000.0],
    'retrait du produit d\'une vente' => [['equity' => 1000.0], ['equity' => 0.0], -200.0],
    'vente partielle puis retrait de son produit' => [['equity' => 1000.0], ['equity' => 500.0], 400.0],
]);
