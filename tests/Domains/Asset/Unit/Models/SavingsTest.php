<?php

use App\Domains\Asset\Models\Assets\Savings;

it('factory creates asset without details', function () {
    $savings = Savings::factory()->create(['name' => 'Savings Account']);

    $savings = Savings::find($savings->id);

    expect($savings)->not->toBeNull()
        ->and($savings->name)->toBe('Savings Account');
});

it('isin returns null for savings', function () {
    $savings = Savings::factory()->create();
    $savings = Savings::find($savings->id);

    expect($savings->isin)->toBeNull();
});

it('ticker returns null for savings', function () {
    $savings = Savings::factory()->create();
    $savings = Savings::find($savings->id);

    expect($savings->ticker)->toBeNull();
});

it('has correct asset type', function () {
    $savings = Savings::factory()->create();
    $savings = Savings::find($savings->id);

    expect($savings->type->value)->toBe('savings');
});
