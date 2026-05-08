<?php

namespace Tests\Feature\Domains\Portfolio\Services;

use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Portfolio\Services\PortfolioPerformanceService;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;
use App\Infrastructure\Support\MarketCalendar;
use Tests\TestCase;

class PortfolioPerformanceServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private PortfolioPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PortfolioPerformanceService::class);
    }

    public function test_computes_security_visibility_with_priced_securities(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $securities = Security::factory()->count(3)->create();

        foreach ($securities as $security) {
            $wallet->transactions()->create([
                'asset_id' => $security->id,
                'type' => 'buy',
                'quantity' => 10,
                'price' => 100.00,
                'fees' => 0,
                'date' => now(),
            ]);
        }

        $lastTradingDate = MarketCalendar::lastTradingDate();
        foreach ($securities as $security) {
            
SecurityPrice::factory()
                ->for($security)
                ->create(['date' => $lastTradingDate, 'close' => 100.00]);
        }

        $result = $this->service->computeSecurityVisibility($wallet, []);

        $this->assertEqualsCanonicalizing([
            $securities[0]->id,
            $securities[1]->id,
            $securities[2]->id,
        ], $result['shown_ids']);
        $this->assertSame([], $result['hidden_ids']);
        $this->assertSame([], $result['priceless_ids']);
    }

    public function test_separates_priceless_securities(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $pricedSecurity = Security::factory()->create();
        $pricelessSecurity = Security::factory()->create();

        $wallet->transactions()->create(['asset_id' => $pricedSecurity->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);
        $wallet->transactions()->create(['asset_id' => $pricelessSecurity->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);

        $lastTradingDate = MarketCalendar::lastTradingDate();
        
SecurityPrice::factory()
            ->for($pricedSecurity)
            ->create(['date' => $lastTradingDate]);

        $result = $this->service->computeSecurityVisibility($wallet, []);

        $this->assertContains($pricedSecurity->id, $result['shown_ids']);
        $this->assertContains($pricelessSecurity->id, $result['priceless_ids']);
    }

    public function test_respects_hidden_security_ids(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $securities = Security::factory()->count(3)->create();

        foreach ($securities as $security) {
            $wallet->transactions()->create(['asset_id' => $security->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);
        }

        $lastTradingDate = MarketCalendar::lastTradingDate();
        foreach ($securities as $security) {
            
SecurityPrice::factory()
                ->for($security)
                ->create(['date' => $lastTradingDate, 'close' => 100.00]);
        }

        $hiddenIds = [$securities[0]->id, $securities[1]->id];
        $result = $this->service->computeSecurityVisibility($wallet, $hiddenIds);

        $this->assertContains($securities[2]->id, $result['shown_ids']);
        $this->assertNotContains($securities[0]->id, $result['shown_ids']);
        $this->assertNotContains($securities[1]->id, $result['shown_ids']);
    }

    public function test_toggles_priceless_security_visibility_with_hidden_ids(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $pricelessSecurity = Security::factory()->create();
        $wallet->transactions()->create(['asset_id' => $pricelessSecurity->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);

        $result = $this->service->computeSecurityVisibility($wallet, [$pricelessSecurity->id]);

        $this->assertContains($pricelessSecurity->id, $result['shown_ids']);
    }

    public function test_calculates_total_valuation_for_shown_securities(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $securities = Security::factory()->count(2)->create();

        foreach ($securities as $security) {
            $wallet->transactions()->create(['asset_id' => $security->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);
        }

        $lastTradingDate = MarketCalendar::lastTradingDate();
        foreach ($securities as $security) {
            
SecurityPrice::factory()
                ->for($security)
                ->create(['date' => $lastTradingDate, 'close' => 100.00]);
        }

        $valuation = $this->service->getTotalValuation($wallet, []);

        $this->assertGreaterThan(0, $valuation);
    }

    public function test_calculates_total_valuation_with_filtered_securities(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security1 = Security::factory()->create();
        $security2 = Security::factory()->create();

        $wallet->transactions()->create(['asset_id' => $security1->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);
        $wallet->transactions()->create(['asset_id' => $security2->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);

        $lastTradingDate = MarketCalendar::lastTradingDate();
        
SecurityPrice::factory()
            ->for($security1)
            ->create(['date' => $lastTradingDate, 'close' => 100.00]);
        
SecurityPrice::factory()
            ->for($security2)
            ->create(['date' => $lastTradingDate, 'close' => 100.00]);

        $valuationAll = $this->service->getTotalValuation($wallet, []);
        $valuationFiltered = $this->service->getTotalValuation($wallet, [$security1->id]);

        $this->assertLessThanOrEqual($valuationAll, $valuationFiltered);
    }

    public function test_returns_default_annualized_return_when_no_wallet(): void
    {
        $return = $this->service->computeAnnualizedReturn(null);

        $this->assertSame(7.0, $return);
    }

    public function test_returns_default_annualized_return_when_no_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        $return = $this->service->computeAnnualizedReturn($wallet);

        $this->assertSame(7.0, $return);
    }

    public function test_calculates_annualized_return_for_wallet_with_transactions(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        $lastTradingDate = MarketCalendar::lastTradingDate();
        
SecurityPrice::factory()
            ->for($security)
            ->create(['date' => $lastTradingDate, 'close' => 150.00]);

        $wallet->transactions()->create([
            'asset_id' => $security->id,
            'type' => 'buy',
            'quantity' => 10,
            'price' => 100.00,
            'fees' => 0,
            'date' => now()->subDays(365),
        ]);

        $return = $this->service->computeAnnualizedReturn($wallet);

        $this->assertGreaterThanOrEqual(0, $return);
        $this->assertLessThanOrEqual(50, $return);
    }

    public function test_returns_default_volatility_when_no_wallet(): void
    {
        $volatility = $this->service->computePortfolioVolatility(null);

        $this->assertSame(15.0, $volatility);
    }

    public function test_delegates_portfolio_volatility_to_calculator(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();

        $volatility = $this->service->computePortfolioVolatility($wallet);

        $this->assertIsFloat($volatility);
        $this->assertGreaterThanOrEqual(0, $volatility);
    }

    public function test_formats_valuation_as_html_currency_string(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        $wallet->transactions()->create(['asset_id' => $security->id, 'type' => 'buy', 'quantity' => 10, 'price' => 100.00, 'fees' => 0, 'date' => now()]);

        $lastTradingDate = MarketCalendar::lastTradingDate();
        
SecurityPrice::factory()
            ->for($security)
            ->create(['date' => $lastTradingDate, 'close' => 100.00]);

        $formatted = $this->service->getFormattedValuation($wallet, []);

        $this->assertStringContainsString('<span class="', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }

    public function test_formats_valuation_with_color_class(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $security = Security::factory()->create();

        $lastTradingDate = MarketCalendar::lastTradingDate();
        
SecurityPrice::factory()
            ->for($security)
            ->create(['date' => $lastTradingDate, 'close' => 100.00]);

        $wallet->transactions()->create([
            'asset_id' => $security->id,
            'type' => 'buy',
            'quantity' => 10,
            'price' => 100.00,
            'fees' => 0,
            'date' => now()->subDays(30),
        ]);

        $formatted = $this->service->getFormattedValuation($wallet, []);

        $this->assertStringContainsString('text-', $formatted);
        $this->assertMatchesRegularExpression('/text-(green|red)-600/', $formatted);
    }
}
