<?php

namespace Tests\Feature\Domains\Portfolio\Listeners;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Events\TransactionCreated;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\User\Models\User;
use Tests\TestCase;

class CalculateRealizedGainListenerTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_calculates_realized_gain_for_sell_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        // Create buy transactions
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

        // Create sell transaction
        $sellTransaction = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120,
                'fees' => 10,
                'realized_gain' => null,
            ]);

        // Dispatch event
        TransactionCreated::dispatch($sellTransaction);

        // Reload from DB
        $sellTransaction->refresh();

        // Expected: (120 - 100) * 10 - 10 = 190
        $this->assertEquals(190, $sellTransaction->realized_gain);
    }

    public function test_sets_realized_gain_null_for_buy_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        $buyTransaction = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100,
                'fees' => 0,
                'realized_gain' => 999, // Should be cleared
            ]);

        // Dispatch event
        TransactionCreated::dispatch($buyTransaction);

        $buyTransaction->refresh();

        $this->assertNull($buyTransaction->realized_gain);
    }

    public function test_calculates_realized_gain_with_multiple_buy_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallets()->create(['name' => 'Test Wallet']);
        $security = Transaction::factory()->for($user)->create()->security;

        // Create two buy transactions with different prices
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
        $sellTransaction = Transaction::factory()
            ->for($user)
            ->for($wallet)
            ->for($security, 'security')
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120,
                'fees' => 5,
                'realized_gain' => null,
            ]);

        TransactionCreated::dispatch($sellTransaction);
        $sellTransaction->refresh();

        // Expected: (120 - 105) * 10 - 5 = 145
        $this->assertEquals(145, $sellTransaction->realized_gain);
    }
}
