<?php

use App\Filament\Resources\AllSecurities\Pages\ListAllSecurities;
use App\Domains\Security\Models\Security;
use App\Models\Transaction;
use App\Domains\User\Models\User;
use App\Models\Wallet;
use App\Domains\Security\Services\YahooFinanceClient;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
});

it('can render the all securities list page', function () {
    $security = Security::factory()->create();

    livewire(ListAllSecurities::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords(collect([$security]));
});

it('shows only the authenticated user transaction count', function () {
    $user = auth()->user();
    $otherUser = User::factory()->create();

    $security = Security::factory()->create();
    $userWallet = Wallet::factory()->create(['user_id' => $user->id]);
    $otherUserWallet = Wallet::factory()->create(['user_id' => $otherUser->id]);

    Transaction::factory()->count(3)->create(['security_id' => $security->id, 'user_id' => $user->id, 'wallet_id' => $userWallet->id]);
    Transaction::factory()->count(5)->create(['security_id' => $security->id, 'user_id' => $otherUser->id, 'wallet_id' => $otherUserWallet->id]);

    livewire(ListAllSecurities::class)
        ->assertTableColumnStateSet('user_transactions_count', 3, $security);
});

it('can search securities by name', function () {
    $target = Security::factory()->create(['name' => 'Tesla Inc.']);
    $other = Security::factory()->create(['name' => 'Amazon']);

    livewire(ListAllSecurities::class)
        ->loadTable()
        ->searchTable('Tesla')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});

it('can update a security from ISIN via table action', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([
                ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'exchange' => 'NMS', 'type' => 'Equity'],
            ]);
        $mock->shouldReceive('fetchPrices')->andReturn([]);
        $mock->shouldReceive('fetchSectors')->andReturn([]);
    });

    $security = Security::factory()->create([
        'isin' => 'US5949181045',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        'MSFT|Microsoft Corporation' => 'MSFT — Microsoft Corporation (NMS)',
    ]);

    livewire(ListAllSecurities::class)
        ->callTableAction(
            'updateFromIsin',
            $security,
            data: [
                'search_options' => $options,
                'selected_result' => 'MSFT|Microsoft Corporation',
            ],
        )
        ->assertHasNoTableActionErrors();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'ticker' => 'MSFT',
        'name' => 'Microsoft Corporation',
    ]);
});

it('shows warning when no results found for ISIN', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([]);
    });

    $security = Security::factory()->create([
        'isin' => 'XX0000000000',
        'name' => null,
        'ticker' => null,
    ]);

    livewire(ListAllSecurities::class)
        ->callTableAction('updateFromIsin', $security)
        ->assertNotified('Aucun résultat trouvé');
});
