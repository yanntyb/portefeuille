<?php

namespace Tests\Feature\Domains\Analytics\Services;

use App\Domains\Analytics\Services\RebalancingCalculator;
use Tests\TestCase;

class RebalancingCalculatorTest extends TestCase
{
    private RebalancingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(RebalancingCalculator::class);
    }

    public function test_rebalances_two_securities_equally(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
        ];

        $result = $this->calculator->calculate($securities, 1000.0);

        $this->assertCount(2, $result['items']);
        $this->assertGreaterThanOrEqual(0, $result['remainder']);
        $totalNewValue = $result['items'][0]['new_value'] + $result['items'][1]['new_value'];
        $this->assertEqualsWithDelta(11000.0, $totalNewValue, 1);
    }

    public function test_handles_zero_investment(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 10,
                'target_percentage' => 100,
            ],
        ];

        $result = $this->calculator->calculate($securities, 0);

        $this->assertSame(10, $result['items'][0]['quantity_held']);
        $this->assertSame(0.0, $result['remainder']);
    }

    public function test_respects_target_percentages(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
        ];

        $result = $this->calculator->calculate($securities, 0);

        $stockAPercentage = $result['items'][0]['new_percentage'];
        $stockBPercentage = $result['items'][1]['new_percentage'];

        $this->assertEqualsWithDelta(50, $stockAPercentage, 1);
        $this->assertEqualsWithDelta(50, $stockBPercentage, 1);
    }

    public function test_handles_single_security(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 10,
                'target_percentage' => 100,
            ],
        ];

        $result = $this->calculator->calculate($securities, 500.0);

        $this->assertCount(1, $result['items']);
        $this->assertSame(10, $result['items'][0]['quantity_held']);
        $this->assertGreaterThan(0, $result['items'][0]['shares_to_buy']);
        $this->assertEqualsWithDelta(100, $result['items'][0]['new_percentage'], 0.1);
    }

    public function test_calculates_shares_to_buy(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 50.0,
                'quantity' => 10,
                'target_percentage' => 100,
            ],
        ];

        $result = $this->calculator->calculate($securities, 500.0);

        $expectedShares = (int) floor(500 / 50);
        $this->assertSame($expectedShares, $result['items'][0]['shares_to_buy']);
    }

    public function test_budget_constraint_adjustment(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
        ];

        $result = $this->calculator->calculate($securities, 150.0);

        $totalSpent = 0;
        foreach ($result['items'] as $item) {
            $totalSpent += $item['buy_cost'];
        }

        $this->assertLessThanOrEqual(150.0, $totalSpent);
    }

    public function test_allocates_remainder(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 100.0,
                'quantity' => 50,
                'target_percentage' => 50,
            ],
        ];

        $result = $this->calculator->calculate($securities, 150.0);

        $this->assertLessThanOrEqual(100.0, $result['remainder']);
        $this->assertGreaterThanOrEqual(0, $result['remainder']);
    }

    public function test_handles_fractional_shares(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 33.33,
                'quantity' => 10,
                'target_percentage' => 100,
            ],
        ];

        $result = $this->calculator->calculate($securities, 100.0);

        $this->assertIsInt($result['items'][0]['shares_to_buy']);
        $this->assertGreaterThanOrEqual(0, $result['remainder']);
    }

    public function test_handles_unequal_targets(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 20,
                'target_percentage' => 20,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 50.0,
                'quantity' => 40,
                'target_percentage' => 80,
            ],
        ];

        $result = $this->calculator->calculate($securities, 1000.0);

        $totalValue = 20 * 100 + 40 * 50 + 1000;
        $this->assertCount(2, $result['items']);
        $this->assertGreaterThan(0, $result['items'][0]['buy_cost'] + $result['items'][1]['buy_cost']);
    }

    public function test_large_portfolio_rebalance(): void
    {
        $securities = [
            [
                'asset_id' => 1,
                'name' => 'Stock A',
                'price' => 100.0,
                'quantity' => 100,
                'target_percentage' => 33,
            ],
            [
                'asset_id' => 2,
                'name' => 'Stock B',
                'price' => 100.0,
                'quantity' => 100,
                'target_percentage' => 33,
            ],
            [
                'asset_id' => 3,
                'name' => 'Stock C',
                'price' => 100.0,
                'quantity' => 100,
                'target_percentage' => 34,
            ],
        ];

        $result = $this->calculator->calculate($securities, 5000.0);

        $this->assertCount(3, $result['items']);
        $totalNewValue = 0;
        foreach ($result['items'] as $item) {
            $totalNewValue += $item['new_value'];
        }
        $this->assertEqualsWithDelta(35000.0, $totalNewValue, 1);
    }
}
