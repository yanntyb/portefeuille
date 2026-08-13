<?php

it('schedules the price sync every day at 23:30', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('30 23 * * *')
        ->expectsOutputToContain('market:sync-prices')
        ->assertSuccessful();
});

it('keeps the weekly sector sync scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('0 0 * * 0')
        ->expectsOutputToContain('market:sync-sectors')
        ->assertSuccessful();
});
