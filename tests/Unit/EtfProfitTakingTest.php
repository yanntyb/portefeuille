<?php

use Database\Seeders\EtfHistorySeeder;

it('sells a fraction of a line whose latent gain clears the threshold', function () {
    // Prix de revient 100 € pour 10 parts, cours 140 € : +40 %, au-dessus du seuil de 30 %.
    expect(EtfHistorySeeder::profitTakingQuantity(10.0, 100.0, 140.0))->toBe(3.0);
});

it('leaves a line untouched while its gain sits under the threshold', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(10.0, 100.0, 129.0))->toBeNull();
});

it('leaves a line untouched exactly at the threshold', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(10.0, 100.0, 130.0))->toBeNull();
});

it('leaves a losing line untouched', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(10.0, 100.0, 60.0))->toBeNull();
});

it('ignores a line that holds nothing', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(0.0, 100.0, 500.0))->toBeNull();
});

it('ignores a line without a cost basis', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(10.0, 0.0, 140.0))->toBeNull();
});

it('rounds the sold quantity to the precision used by buys', function () {
    // 30 % de 1,23456789 = 0,370370367, arrondi à quatre décimales comme les achats.
    expect(EtfHistorySeeder::profitTakingQuantity(1.23456789, 10.0, 20.0))->toBe(0.3704);
});

it('sells nothing when the fraction rounds down to zero', function () {
    expect(EtfHistorySeeder::profitTakingQuantity(0.0001, 10.0, 20.0))->toBeNull();
});
