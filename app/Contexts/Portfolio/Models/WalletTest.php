<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Wallet;

it('belongs to a user and persists a name', function () {
    $user = User::factory()->create();

    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);

    expect($wallet->name)->toBe('PEA')
        ->and($wallet->user->is($user))->toBeTrue();
});
