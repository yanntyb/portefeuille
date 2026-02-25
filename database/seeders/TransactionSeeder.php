<?php

namespace Database\Seeders;

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * @var array<string, array{isin: string, ticker: string, name: string, start_price: float, drift: float, volatility: float, sectors: array<string, float>}>
     */
    private const ETFS = [
        'world' => [
            'isin' => 'LU1681043599',
            'ticker' => 'CW8.PA',
            'name' => 'Amundi MSCI World UCITS ETF',
            'start_price' => 320.0,
            'drift' => 0.00035,
            'volatility' => 0.011,
            'sectors' => [
                'technology' => 0.235,
                'financial_services' => 0.155,
                'healthcare' => 0.120,
                'consumer_cyclical' => 0.105,
                'industrials' => 0.105,
                'communication_services' => 0.075,
                'consumer_defensive' => 0.065,
                'energy' => 0.050,
                'basic_materials' => 0.040,
                'realestate' => 0.025,
                'utilities' => 0.025,
            ],
        ],
        'sp500' => [
            'isin' => 'FR0011871128',
            'ticker' => 'PE500.PA',
            'name' => 'Amundi PEA S&P 500 UCITS ETF',
            'start_price' => 28.0,
            'drift' => 0.00040,
            'volatility' => 0.012,
            'sectors' => [
                'technology' => 0.295,
                'financial_services' => 0.135,
                'healthcare' => 0.125,
                'consumer_cyclical' => 0.105,
                'communication_services' => 0.090,
                'industrials' => 0.085,
                'consumer_defensive' => 0.060,
                'energy' => 0.040,
                'realestate' => 0.025,
                'utilities' => 0.025,
                'basic_materials' => 0.015,
            ],
        ],
        'emerging' => [
            'isin' => 'FR0013412020',
            'ticker' => 'PAEEM.PA',
            'name' => 'Amundi PEA MSCI Emerging Markets UCITS ETF',
            'start_price' => 19.0,
            'drift' => 0.00015,
            'volatility' => 0.014,
            'sectors' => [
                'technology' => 0.220,
                'financial_services' => 0.210,
                'consumer_cyclical' => 0.135,
                'communication_services' => 0.100,
                'industrials' => 0.070,
                'energy' => 0.065,
                'basic_materials' => 0.065,
                'consumer_defensive' => 0.055,
                'healthcare' => 0.040,
                'utilities' => 0.025,
                'realestate' => 0.015,
            ],
        ],
        'europe' => [
            'isin' => 'FR0007085501',
            'ticker' => 'MEUD.PA',
            'name' => 'Amundi PEA STOXX Europe 600 UCITS ETF',
            'start_price' => 22.0,
            'drift' => 0.00025,
            'volatility' => 0.010,
            'sectors' => [
                'financial_services' => 0.185,
                'industrials' => 0.170,
                'healthcare' => 0.145,
                'consumer_cyclical' => 0.115,
                'technology' => 0.085,
                'consumer_defensive' => 0.080,
                'energy' => 0.060,
                'basic_materials' => 0.055,
                'utilities' => 0.045,
                'communication_services' => 0.035,
                'realestate' => 0.025,
            ],
        ],
        'nasdaq' => [
            'isin' => 'FR0013412269',
            'ticker' => 'PANX.PA',
            'name' => 'Amundi PEA Nasdaq-100 UCITS ETF',
            'start_price' => 40.0,
            'drift' => 0.00050,
            'volatility' => 0.015,
            'sectors' => [
                'technology' => 0.490,
                'communication_services' => 0.165,
                'consumer_cyclical' => 0.145,
                'healthcare' => 0.070,
                'consumer_defensive' => 0.055,
                'industrials' => 0.045,
                'utilities' => 0.015,
                'financial_services' => 0.015,
            ],
        ],
    ];

    public function run(User $user): void
    {
        $startDate = CarbonImmutable::parse('2021-03-01');
        $endDate = CarbonImmutable::now();
        $monthlyBudget = 500.0;

        $securities = [];

        foreach (self::ETFS as $key => $etf) {
            $security = Security::factory()->create([
                'isin' => $etf['isin'],
                'name' => $etf['name'],
                'ticker' => $etf['ticker'],
            ]);

            $securities[$key] = $security;

            $this->seedPriceHistory($security, $etf['start_price'], $etf['drift'], $etf['volatility'], $startDate);
            $this->seedSectors($security, $etf['sectors']);
        }

        $this->seedDcaTransactions($user, $securities, $startDate, $endDate, $monthlyBudget);
    }

    /**
     * @param  array<string, Security>  $securities
     */
    private function seedDcaTransactions(User $user, array $securities, CarbonImmutable $startDate, CarbonImmutable $endDate, float $monthlyBudget): void
    {
        $budgetPerEtf = $monthlyBudget / count($securities);
        $date = $startDate;
        $transactions = [];

        while ($date->lte($endDate)) {
            $investDate = $date->day(15);

            if ($investDate->isWeekend()) {
                $investDate = $investDate->next(CarbonImmutable::MONDAY);
            }

            if ($investDate->gt($endDate)) {
                break;
            }

            foreach ($securities as $security) {
                $price = SecurityPrice::query()
                    ->where('security_id', $security->id)
                    ->where('date', '<=', $investDate->toDateString())
                    ->orderByDesc('date')
                    ->value('close');

                if ($price === null) {
                    continue;
                }

                $price = (float) $price;
                $quantity = floor($budgetPerEtf / $price * 10000) / 10000;

                if ($quantity <= 0) {
                    continue;
                }

                $fees = round($quantity * $price * 0.002, 2);

                $transactions[] = [
                    'user_id' => $user->id,
                    'date' => $investDate->toDateString(),
                    'account_type' => 'pea',
                    'security_id' => $security->id,
                    'broker' => null,
                    'quantity' => $quantity,
                    'unit_price' => round($price, 4),
                    'fees' => $fees,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $date = $date->addMonth();
        }

        foreach (array_chunk($transactions, 100) as $chunk) {
            Transaction::insert($chunk);
        }
    }

    /**
     * @param  array<string, float>  $sectors
     */
    private function seedSectors(Security $security, array $sectors): void
    {
        $rows = [];

        foreach ($sectors as $sectorValue => $weight) {
            $rows[] = [
                'security_id' => $security->id,
                'sector' => $sectorValue,
                'weight' => $weight,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SecuritySector::insert($rows);
    }

    private function seedPriceHistory(Security $security, float $startPrice, float $dailyDrift, float $volatility, CarbonImmutable $startDate): void
    {
        $date = $startDate->subMonth();
        $endDate = CarbonImmutable::now();
        $close = $startPrice;
        $rows = [];

        while ($date->lte($endDate)) {
            if ($date->isWeekday()) {
                $randomShock = $dailyDrift + $volatility * $this->gaussianRandom();
                $close = round($close * (1 + $randomShock), 4);
                $dayVolatility = $close * $volatility;
                $high = round($close + abs($dayVolatility * mt_rand(10, 80) / 100), 4);
                $low = round($close - abs($dayVolatility * mt_rand(10, 80) / 100), 4);
                $open = round($low + ($high - $low) * mt_rand(20, 80) / 100, 4);

                $rows[] = [
                    'security_id' => $security->id,
                    'date' => $date->toDateString(),
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'close' => $close,
                    'volume' => mt_rand(50000, 500000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $date = $date->addDay();
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SecurityPrice::insert($chunk);
        }
    }

    private function gaussianRandom(): float
    {
        $u1 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $u2 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
