<?php

use App\Domains\Asset\Models\Assets\Savings;

it('factory creates asset without details', function () {
    $savings = Savings::factory()->create(['name' => 'Savings Account']);

    $savings = Savings::find($savings->id);

    expect($savings)->not->toBeNull()
        ->and($savings->name)->toBe('Savings Account');
});
