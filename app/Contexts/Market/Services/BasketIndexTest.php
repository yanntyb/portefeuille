<?php

use App\Contexts\Market\Services\BasketIndex;

it('suit les rendements de l’instrument quand le panier n’en tient qu’un', function () {
    $index = (new BasketIndex)->of(
        closesByKey: [7 => ['2024-01-01' => 50.0, '2024-01-02' => 55.0, '2024-01-03' => 44.0]],
        weightsByKey: [7 => 1.0],
    );

    expect($index->labels)->toBe(['2024-01-01', '2024-01-02', '2024-01-03'])
        ->and($index->values)->toBe([100.0, 110.0, 88.0]);
});

it('moyenne les rendements de deux instruments de même poids', function () {
    $index = (new BasketIndex)->of(
        closesByKey: [
            7 => ['2024-01-01' => 100.0, '2024-01-02' => 120.0],
            9 => ['2024-01-01' => 50.0, '2024-01-02' => 50.0],
        ],
        weightsByKey: [7 => 1000.0, 9 => 1000.0],
    );

    expect($index->values)->toBe([100.0, 110.0]);
});

it('pèse chaque rendement selon la place de l’instrument dans le panier', function () {
    $index = (new BasketIndex)->of(
        closesByKey: [
            7 => ['2024-01-01' => 100.0, '2024-01-02' => 120.0],
            9 => ['2024-01-01' => 50.0, '2024-01-02' => 50.0],
        ],
        weightsByKey: [7 => 3000.0, 9 => 1000.0],
    );

    expect($index->values)->toBe([100.0, 115.0]);
});

it('n’attend pas le plus jeune instrument pour commencer l’indice', function () {
    /**
     * Le second n'entre qu'au troisième jour : l'indice court depuis le premier, porté par le seul
     * instrument coté, plutôt que de perdre deux séances d'histoire.
     */
    $index = (new BasketIndex)->of(
        closesByKey: [
            7 => ['2024-01-01' => 100.0, '2024-01-02' => 110.0, '2024-01-03' => 110.0, '2024-01-04' => 110.0],
            9 => ['2024-01-03' => 50.0, '2024-01-04' => 60.0],
        ],
        weightsByKey: [7 => 1000.0, 9 => 1000.0],
    );

    expect($index->labels)->toBe(['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04'])
        ->and($index->values)->toBe([100.0, 110.0, 110.0, 121.0]);
});

it('reporte le dernier cours d’un instrument déjà entré qui manque une séance', function () {
    /**
     * Le second ne cote pas le troisième jour — place fermée, publication en retard. Sans report,
     * les poids se renormaliseraient sur le seul premier et l'indice prendrait ses +10 % entiers ;
     * avec report, le second apporte un rendement nul et l'indice ne monte que de la moitié.
     */
    $index = (new BasketIndex)->of(
        closesByKey: [
            7 => ['2024-01-01' => 100.0, '2024-01-02' => 100.0, '2024-01-03' => 110.0],
            9 => ['2024-01-01' => 50.0, '2024-01-02' => 50.0],
        ],
        weightsByKey: [7 => 1000.0, 9 => 1000.0],
    );

    expect($index->values)->toBe([100.0, 100.0, 105.0]);
});

it('ignore un instrument sans poids connu', function () {
    $index = (new BasketIndex)->of(
        closesByKey: [
            7 => ['2024-01-01' => 100.0, '2024-01-02' => 120.0],
            9 => ['2024-01-01' => 50.0, '2024-01-02' => 25.0],
        ],
        weightsByKey: [7 => 1000.0],
    );

    expect($index->values)->toBe([100.0, 120.0]);
});

it('ne rend aucun indice sans instrument', function () {
    $index = (new BasketIndex)->of(closesByKey: [], weightsByKey: []);

    expect($index->labels)->toBe([])
        ->and($index->values)->toBe([]);
});

it('ne rend aucun indice quand le panier ne pèse rien', function () {
    $index = (new BasketIndex)->of(
        closesByKey: [7 => ['2024-01-01' => 100.0, '2024-01-02' => 120.0]],
        weightsByKey: [7 => 0.0],
    );

    expect($index->labels)->toBe([])
        ->and($index->values)->toBe([]);
});
