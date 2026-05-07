<?php

namespace Tests\Feature\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\User\Models\User;
use Tests\TestCase;

class CalculateRealizedGainListenerTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_listener_receives_transaction_created_event(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        // Create sell transaction (observer calculates realized gain)
        $sellTransaction = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120,
                'fees' => 10,
            ]);

        // Realized gain was already calculated by observer
        $this->assertNotNull($sellTransaction->realized_gain);
    }

    public function test_observer_calculates_realized_gain_for_sell(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        // Create buy
        Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100,
                'fees' => 0,
            ]);

        // Create sell (observer calculates)
        $sell = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120,
                'fees' => 10,
            ]);

        // Expected: (120 - 100) * 10 - 10 = 190
        $this->assertEquals(190, $sell->realized_gain);
    }

    public function test_observer_sets_realized_gain_null_for_buy(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        $buy = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100,
                'fees' => 0,
            ]);

        $this->assertNull($buy->realized_gain);
    }

    public function test_observer_calculates_with_multiple_buys(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        // Two buy transactions
        Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 5,
                'unit_price' => 100,
                'fees' => 0,
            ]);

        Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 5,
                'unit_price' => 110,
                'fees' => 0,
            ]);

        // PRU = (5*100 + 5*110) / 10 = 105
        $sell = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120,
                'fees' => 5,
            ]);

        // Expected: (120 - 105) * 10 - 5 = 145
        $this->assertEquals(145, $sell->realized_gain);
    }
}
