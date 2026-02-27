<?php

use App\Enums\AccountType;
use App\Filament\Pages\RebalancingCalculator;
use App\Models\AllocationProfile;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RebalancingCalculator as RebalancingService;

use function Pest\Livewire\livewire;

// --- Service tests ---

it('calculates shares to buy for a simple 60/40 split', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'NASDAQ ETF',
            'price' => 50.0,
            'quantity' => 0,
            'target_percentage' => 60.0,
        ],
        [
            'security_id' => 2,
            'name' => 'STOXX ETF',
            'price' => 30.0,
            'quantity' => 0,
            'target_percentage' => 40.0,
        ],
    ], 500.0);

    expect($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['shares_to_buy'])->toBe(6)
        ->and($result['items'][1]['shares_to_buy'])->toBe(6)
        ->and($result['remainder'])->toBeGreaterThanOrEqual(0)
        ->and($result['total_invested'] + $result['remainder'])->toBe(500.0);
});

it('considers existing holdings when calculating', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'NASDAQ ETF',
            'price' => 100.0,
            'quantity' => 10,
            'target_percentage' => 60.0,
        ],
        [
            'security_id' => 2,
            'name' => 'STOXX ETF',
            'price' => 50.0,
            'quantity' => 5,
            'target_percentage' => 40.0,
        ],
    ], 500.0);

    $nasdaqItem = $result['items'][0];
    $stoxxItem = $result['items'][1];

    expect($nasdaqItem['current_value'])->toBe(1000.0)
        ->and($stoxxItem['current_value'])->toBe(250.0)
        ->and($result['total_invested'] + $result['remainder'])->toBe(500.0);
});

it('uses greedy allocation for remainder', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'Cheap ETF',
            'price' => 10.0,
            'quantity' => 0,
            'target_percentage' => 50.0,
        ],
        [
            'security_id' => 2,
            'name' => 'Expensive ETF',
            'price' => 10.0,
            'quantity' => 0,
            'target_percentage' => 50.0,
        ],
    ], 100.0);

    $totalShares = $result['items'][0]['shares_to_buy'] + $result['items'][1]['shares_to_buy'];
    expect($totalShares)->toBe(10)
        ->and($result['remainder'])->toBe(0.0);
});

it('never buys negative shares', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'Over-allocated',
            'price' => 100.0,
            'quantity' => 100,
            'target_percentage' => 10.0,
        ],
        [
            'security_id' => 2,
            'name' => 'Under-allocated',
            'price' => 50.0,
            'quantity' => 0,
            'target_percentage' => 90.0,
        ],
    ], 500.0);

    expect($result['items'][0]['shares_to_buy'])->toBe(0)
        ->and($result['items'][1]['shares_to_buy'])->toBeGreaterThan(0);
});

it('never exceeds the investment amount', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'NASDAQ ETF',
            'price' => 65.53,
            'quantity' => 137.77,
            'target_percentage' => 60.0,
        ],
        [
            'security_id' => 2,
            'name' => 'STOXX ETF',
            'price' => 305.40,
            'quantity' => 239.75,
            'target_percentage' => 40.0,
        ],
    ], 500.0);

    expect($result['total_invested'])->toBeLessThanOrEqual(500.0)
        ->and($result['remainder'])->toBeGreaterThanOrEqual(0);
});

it('handles a single security at 100%', function () {
    $calculator = new RebalancingService;

    $result = $calculator->calculate([
        [
            'security_id' => 1,
            'name' => 'Only ETF',
            'price' => 75.0,
            'quantity' => 0,
            'target_percentage' => 100.0,
        ],
    ], 500.0);

    expect($result['items'][0]['shares_to_buy'])->toBe(6)
        ->and($result['remainder'])->toBe(50.0)
        ->and($result['total_invested'])->toBe(450.0);
});

// --- Page tests ---

it('can render the rebalancing calculator page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(RebalancingCalculator::getUrl())
        ->assertSuccessful();
});

it('validates that total percentage equals 100', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'close' => 50.0,
    ]);

    $this->actingAs($user);

    livewire(RebalancingCalculator::class)
        ->fillForm([
            'amount' => 500,
            'allocations' => [
                ['security_id' => $security->id, 'target_percentage' => 30],
            ],
        ])
        ->call('calculate')
        ->assertNotified('Le total des pourcentages doit être égal à 100%');
});

it('calculates and displays results', function () {
    $user = User::factory()->create();
    $security1 = Security::factory()->create(['name' => 'NASDAQ ETF']);
    $security2 = Security::factory()->create(['name' => 'STOXX ETF']);

    SecurityPrice::factory()->create(['security_id' => $security1->id, 'close' => 50.0]);
    SecurityPrice::factory()->create(['security_id' => $security2->id, 'close' => 30.0]);

    $this->actingAs($user);

    $component = livewire(RebalancingCalculator::class)
        ->fillForm([
            'amount' => 500,
            'allocations' => [
                ['security_id' => $security1->id, 'target_percentage' => 60],
                ['security_id' => $security2->id, 'target_percentage' => 40],
            ],
        ])
        ->call('calculate');

    expect($component->get('hasResults'))->toBeTrue()
        ->and($component->get('resultItems'))->toHaveCount(2)
        ->and($component->get('remainder'))->toBeGreaterThanOrEqual(0);
});

it('can save and load a profile', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100.0]);

    $this->actingAs($user);

    livewire(RebalancingCalculator::class)
        ->fillForm([
            'amount' => 500,
            'account_type' => '',
            'allocations' => [
                ['security_id' => $security->id, 'target_percentage' => 100],
            ],
        ])
        ->call('saveProfile')
        ->assertNotified('Profil sauvegardé');

    $profile = AllocationProfile::query()
        ->where('user_id', $user->id)
        ->first();

    expect($profile)->not->toBeNull()
        ->and($profile->items)->toHaveCount(1)
        ->and((float) $profile->items->first()->target_percentage)->toBe(100.0);
});

it('considers account type when calculating quantities', function () {
    $user = User::factory()->create();
    $security = Security::factory()->create();
    SecurityPrice::factory()->create(['security_id' => $security->id, 'close' => 100.0]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'account_type' => AccountType::Pea,
        'quantity' => 5,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'security_id' => $security->id,
        'account_type' => AccountType::Cto,
        'quantity' => 3,
    ]);

    $this->actingAs($user);

    $componentPea = livewire(RebalancingCalculator::class)
        ->fillForm([
            'amount' => 500,
            'account_type' => 'pea',
            'allocations' => [
                ['security_id' => $security->id, 'target_percentage' => 100],
            ],
        ])
        ->call('calculate');

    expect($componentPea->get('resultItems.0.quantity_held'))->toBe(5.0);

    $componentGlobal = livewire(RebalancingCalculator::class)
        ->fillForm([
            'amount' => 500,
            'account_type' => '',
            'allocations' => [
                ['security_id' => $security->id, 'target_percentage' => 100],
            ],
        ])
        ->call('calculate');

    expect($componentGlobal->get('resultItems.0.quantity_held'))->toBe(8.0);
});
