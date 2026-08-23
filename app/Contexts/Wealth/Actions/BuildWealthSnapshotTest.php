<?php

use App\Contexts\Wealth\Actions\BuildWealthSnapshot;

it('rassemble les trois blocs du tableau de bord', function () {
    ['user' => $user] = portfolioFixture();

    $snapshot = app(BuildWealthSnapshot::class)($user->id);

    expect($snapshot)->toHaveKeys(['overview', 'series', 'income'])
        ->and($snapshot['overview']->totalValue)->toBeGreaterThan(0.0);
});
