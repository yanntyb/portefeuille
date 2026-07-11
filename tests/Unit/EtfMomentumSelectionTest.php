<?php

use Database\Seeders\EtfHistorySeeder;

it('picks the asset with the strongest positive momentum', function () {
    expect(EtfHistorySeeder::selectWinner([10 => 0.05, 20 => 0.12, 30 => 0.03]))->toBe(20);
});

it('returns null when every momentum is negative or flat', function () {
    expect(EtfHistorySeeder::selectWinner([10 => -0.05, 20 => -0.12, 30 => 0.0]))->toBeNull();
});

it('returns null on an empty candidate set', function () {
    expect(EtfHistorySeeder::selectWinner([]))->toBeNull();
});

it('picks the single positive candidate among negatives', function () {
    expect(EtfHistorySeeder::selectWinner([10 => -0.20, 20 => 0.01, 30 => -0.05]))->toBe(20);
});
