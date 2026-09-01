<?php

use App\Contexts\Wealth\Services\InvestedCapital;

it('impute tout l\'apport à l\'exposition tant qu\'il y est immobilisé', function () {
    /** Apport 1 000, achat 1 000 : l'exposition porte les 1 000, la caisse rien. */
    $capital = new InvestedCapital;

    $exposure = $capital->forExposure(imputedContributions: 1000.0, costOfHoldings: 1000.0);

    expect($exposure)->toBe(1000.0)
        ->and($capital->forCash(netContributions: 1000.0, exposuresInvested: $exposure, cash: 0.0))->toBe(0.0);
});

it('rend l\'apport aux liquidités dès que les titres sont vendus', function () {
    /** … puis vente 1 200, rien racheté : plus aucun titre à immobiliser l'apport. */
    $capital = new InvestedCapital;

    $exposure = $capital->forExposure(imputedContributions: 1000.0, costOfHoldings: 0.0);

    expect($exposure)->toBe(0.0)
        ->and($capital->forCash(netContributions: 1000.0, exposuresInvested: $exposure, cash: 1200.0))->toBe(1000.0);
});

it('ne recompte aucun apport quand le produit d\'une vente est réemployé', function () {
    /**
     * … puis rachat 1 200 : le coût des titres remonte à 1 200, mais l'apport imputé reste 1 000 —
     * l'aller-retour n'a rien sorti de la poche du porteur. C'est le cas que `totalCost` faisait
     * afficher « Investi 1 200, Gain 0 € ».
     */
    $capital = new InvestedCapital;

    $exposure = $capital->forExposure(imputedContributions: 1000.0, costOfHoldings: 1200.0);

    expect($exposure)->toBe(1000.0)
        ->and($capital->forCash(netContributions: 1000.0, exposuresInvested: $exposure, cash: 0.0))->toBe(0.0);
});

it('garde à chaque exposition financée séparément le sien, et le reste aux liquidités', function () {
    $capital = new InvestedCapital;

    $equity = $capital->forExposure(imputedContributions: 1000.0, costOfHoldings: 1000.0);
    $crypto = $capital->forExposure(imputedContributions: 500.0, costOfHoldings: 500.0);

    expect($equity)->toBe(1000.0)
        ->and($crypto)->toBe(500.0)
        ->and($capital->forCash(netContributions: 1800.0, exposuresInvested: $equity + $crypto, cash: 300.0))->toBe(300.0);
});

/**
 * L'invariant du chantier : la somme des investis de toutes les classes fait exactement les apports
 * nets, et aucune classe ne part en gain négatif faute d'avoir rendu son capital.
 */
it('répartit les apports nets sans en perdre ni en inventer', function (
    float $netContributions,
    array $imputed,
    array $costs,
    float $cash,
) {
    $capital = new InvestedCapital;

    $exposures = 0.0;

    foreach ($imputed as $key => $contribution) {
        $exposures = round($exposures + $capital->forExposure($contribution, $costs[$key]), 2);
    }

    $total = round($exposures + $capital->forCash($netContributions, $exposures, $cash), 2);

    expect($total)->toBe($netContributions);
})->with([
    'apport 1 000, achat 1 000' => [1000.0, ['equity' => 1000.0], ['equity' => 1000.0], 0.0],
    'vente 1 200, rien racheté' => [1000.0, ['equity' => 1000.0], ['equity' => 0.0], 1200.0],
    'rachat 1 200' => [1000.0, ['equity' => 1000.0], ['equity' => 1200.0], 0.0],
    'deux expositions financées séparément' => [1500.0, ['equity' => 1000.0, 'crypto' => 500.0], ['equity' => 1000.0, 'crypto' => 500.0], 0.0],
    'vente de la moitié' => [1000.0, ['equity' => 1000.0], ['equity' => 500.0], 600.0],
    'apport 1 000, retrait 300' => [700.0, [], [], 700.0],
]);
