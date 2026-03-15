<?php

use App\Filament\Resources\PortfolioSecurities\Pages\ListPortfolioSecurities;
use App\Models\Security;
use App\Models\Transaction;
use App\Models\User;

use function Pest\Livewire\livewire;

it('can render the portfolio list page with securities from all account types', function () {
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $peaSecurity->id]);

    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $ctoSecurity->id]);

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$peaSecurity, $ctoSecurity]));
});

it('does not show securities without transactions', function () {
    $securityWithTx = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $securityWithTx->id]);

    $securityWithoutTx = Security::factory()->create();

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$securityWithTx]))
        ->assertCanNotSeeTableRecords(collect([$securityWithoutTx]));
});

it('does not show securities from other users', function () {
    $mySecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $mySecurity->id]);

    $otherSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create([
        'security_id' => $otherSecurity->id,
        'user_id' => User::factory()->create()->id,
    ]);

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$mySecurity]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});
