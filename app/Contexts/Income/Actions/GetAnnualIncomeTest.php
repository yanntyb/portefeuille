<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;

it('groupe le revenu par année, de la plus ancienne à la plus récente', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $years = app(GetAnnualIncome::class)($user->id);

    expect($years)->toHaveCount(2)
        ->and($years[0]->year)->toBe(2025)
        ->and($years[0]->total)->toBe(5.0)
        ->and($years[0]->bySource)->toBe(['dividend' => 5.0])
        ->and($years[1]->year)->toBe(2026)
        ->and($years[1]->total)->toBe(8.0);
});

it('rend un tableau vide sans revenu', function () {
    expect(app(GetAnnualIncome::class)(User::factory()->create()->id))->toBe([]);
});
