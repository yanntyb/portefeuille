<?php

use App\Contexts\Portfolio\Datas\ConcentrationData;
use App\Contexts\Portfolio\Services\Concentration;

it('mesure la concentration de quatre positions égales', function () {
    $concentration = (new Concentration)->of([250.0, 250.0, 250.0, 250.0]);

    expect($concentration->top1)->toBe(25.0)
        ->and($concentration->top3)->toBe(75.0)
        ->and($concentration->top5)->toBe(100.0)
        ->and($concentration->hhi)->toBe(0.25);
});

it('classe les positions par valeur décroissante avant de cumuler', function () {
    $concentration = (new Concentration)->of([100.0, 700.0, 200.0]);

    expect($concentration->top1)->toBe(70.0)
        ->and($concentration->top3)->toBe(100.0);
});

it('rend un top complet même avec moins de positions que le rang demandé', function () {
    $concentration = (new Concentration)->of([600.0, 400.0]);

    expect($concentration->top3)->toBe(100.0)
        ->and($concentration->top5)->toBe(100.0);
});

it('rend un HHI de un sur une position unique', function () {
    expect((new Concentration)->of([1000.0])->hhi)->toBe(1.0);
});

it('exclut les positions sans cours connu au lieu de les compter à zéro', function () {
    $concentration = (new Concentration)->of([500.0, 500.0, null]);

    expect($concentration->top1)->toBe(50.0)
        ->and($concentration->hhi)->toBe(0.5);
});

it('ne définit aucune concentration sur un portefeuille vide', function () {
    expect((new Concentration)->of([]))->toEqual(ConcentrationData::empty());
});

it('ne définit aucune concentration quand aucune position n\'a de cours', function () {
    expect((new Concentration)->of([null, null]))->toEqual(ConcentrationData::empty());
});

it('ignore les valeurs négatives ou nulles, qui ne sont pas des expositions', function () {
    $concentration = (new Concentration)->of([500.0, 500.0, 0.0]);

    expect($concentration->top1)->toBe(50.0);
});

it('exclut les valeurs négatives, qui ne sont pas des expositions', function () {
    $concentration = (new Concentration)->of([500.0, 500.0, -200.0]);

    expect($concentration->top1)->toBe(50.0)
        ->and($concentration->hhi)->toBe(0.5);
});

it('verrouille l\'ordre des clés de jsonSerialize dans l\'ordre spécifié', function () {
    $data = ConcentrationData::empty();
    $serialized = $data->jsonSerialize();
    $keys = array_keys($serialized);

    expect($keys)->toBe(['top1', 'top3', 'top5', 'hhi']);
});
