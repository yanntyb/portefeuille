<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;
use App\Filament\Resources\PortfolioSecurities\Pages\ListPortfolioSecurities;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
});

it('can render the portfolio list page with securities from all account types', function () {
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $peaSecurity->id]);

    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $ctoSecurity->id]);

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$peaSecurity, $ctoSecurity]));
});

it('does not show securities without transactions', function () {
    $securityWithTx = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $securityWithTx->id]);

    $securityWithoutTx = Security::factory()->create();

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$securityWithTx]))
        ->assertCanNotSeeTableRecords(collect([$securityWithoutTx]));
});

it('does not show securities from other users', function () {
    $mySecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $mySecurity->id]);

    $otherUser = User::factory()->create();
    $otherWallet = Wallet::factory()->pea()->create(['user_id' => $otherUser->id]);
    $otherSecurity = Security::factory()->create();
    Transaction::factory()->create([
        'security_id' => $otherSecurity->id,
        'user_id' => $otherUser->id,
        'wallet_id' => $otherWallet->id,
    ]);

    livewire(ListPortfolioSecurities::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$mySecurity]))
        ->assertCanNotSeeTableRecords(collect([$otherSecurity]));
});
