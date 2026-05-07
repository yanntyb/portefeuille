<?php

namespace Tests\Feature\Domains\Portfolio\Services;

use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Services\RealizedGainCalculator;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;
use Tests\TestCase;

class RealizedGainCalculatorTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private RealizedGainCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(RealizedGainCalculator::class);
    }

    public function test_returns_null_for_buy_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        $buyTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($buyTransaction);

        $this->assertNull($result);
    }

    public function test_calculates_realized_gain_simple_sale(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(200.0, $result);
    }

    public function test_calculates_realized_loss(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 80.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(-200.0, $result);
    }

    public function test_deducts_fees_from_gain(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120.00,
                'fees' => 50.00,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(150.0, $result);
    }

    public function test_calculates_multiple_buy_average_cost(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 20,
                'unit_price' => 130.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $avgCost = (10 * 100.00 + 10 * 120.00) / 20;
        $expectedGain = (130.00 - $avgCost) * 20;

        $this->assertSame(round($expectedGain, 2), $result);
    }

    public function test_handles_partial_sale(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 50,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 20,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(400.0, $result);
    }

    public function test_handles_no_buy_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(1000.0, $result);
    }

    public function test_isolates_transactions_by_wallet(): void
    {
        $user = User::factory()->create();
        $wallet1 = Wallet::factory()->for($user)->create();
        $wallet2 = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet1)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        Transaction::factory()
            ->for($wallet2)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 150.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet1)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(200.0, $result);
    }

    public function test_isolates_transactions_by_security(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security1 = Security::factory()->create();
        $security2 = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security1)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        Transaction::factory()
            ->for($wallet)
            ->for($security2)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10,
                'unit_price' => 50.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security1)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 10,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertSame(200.0, $result);
    }

    public function test_handles_fractional_quantity(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Buy,
                'quantity' => 10.5,
                'unit_price' => 100.00,
                'fees' => 0,
            ]);

        $sellTransaction = Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create([
                'type' => TransactionType::Sell,
                'quantity' => 5.25,
                'unit_price' => 120.00,
                'fees' => 0,
            ]);

        $result = $this->calculator->calculate($sellTransaction);

        $this->assertEqualsWithDelta(105.0, $result, 0.01);
    }
}
