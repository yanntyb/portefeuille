<?php

namespace Database\Seeders;

use App\Enums\CurrencyModificationUnit;
use App\Enums\FeeScope;
use App\Enums\Sector;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * @var array<string, array{isin: string, ticker: string, name: string}>
     */
    private const PEA_ETFS = [
        'world' => [
            'isin' => 'LU1681043599',
            'ticker' => 'CW8.PA',
            'name' => 'Amundi MSCI World UCITS ETF',
        ],
        'sp500' => [
            'isin' => 'FR0011871128',
            'ticker' => 'PE500.PA',
            'name' => 'Amundi PEA S&P 500 UCITS ETF',
        ],
        'emerging' => [
            'isin' => 'FR0013412020',
            'ticker' => 'PAEEM.PA',
            'name' => 'Amundi PEA MSCI Emerging Markets UCITS ETF',
        ],
        'europe' => [
            'isin' => 'FR0007085501',
            'ticker' => 'MEUD.PA',
            'name' => 'Amundi PEA STOXX Europe 600 UCITS ETF',
        ],
        'nasdaq' => [
            'isin' => 'FR0013412269',
            'ticker' => 'PANX.PA',
            'name' => 'Amundi PEA Nasdaq-100 UCITS ETF',
        ],
    ];

    /**
     * @var array<string, array{isin: string, ticker: string, name: string}>
     */
    private const CTO_ETFS = [
        'world' => [
            'isin' => 'IE00B4L5Y983',
            'ticker' => 'IWDA.L',
            'name' => 'iShares Core MSCI World UCITS ETF',
        ],
        'sp500' => [
            'isin' => 'IE00B5BMR087',
            'ticker' => 'CSPX.L',
            'name' => 'iShares Core S&P 500 UCITS ETF',
        ],
        'emerging' => [
            'isin' => 'IE00BKM4GZ66',
            'ticker' => 'EIMI.L',
            'name' => 'iShares Core MSCI EM IMI UCITS ETF',
        ],
    ];

    /**
     * @var array<string, array{base_price: float, volatility: float}>
     */
    private const ETF_CONFIGS = [
        'CW8.PA' => ['base_price' => 55.0, 'volatility' => 0.012],
        'PE500.PA' => ['base_price' => 28.0, 'volatility' => 0.013],
        'PAEEM.PA' => ['base_price' => 22.0, 'volatility' => 0.014],
        'MEUD.PA' => ['base_price' => 62.0, 'volatility' => 0.011],
        'PANX.PA' => ['base_price' => 42.0, 'volatility' => 0.015],
        'IWDA.L' => ['base_price' => 62.0, 'volatility' => 0.012],
        'CSPX.L' => ['base_price' => 380.0, 'volatility' => 0.013],
        'EIMI.L' => ['base_price' => 28.0, 'volatility' => 0.014],
    ];

    /**
     * @return array<string, array<string, float>>
     */
    private static function sectorAllocations(): array
    {
        return [
            'world' => [
                Sector::Technology->value => 0.23,
                Sector::Healthcare->value => 0.12,
                Sector::FinancialServices->value => 0.15,
                Sector::CommunicationServices->value => 0.08,
                Sector::ConsumerCyclical->value => 0.11,
                Sector::ConsumerDefensive->value => 0.07,
                Sector::Industrials->value => 0.10,
                Sector::Energy->value => 0.05,
                Sector::Utilities->value => 0.03,
                Sector::RealEstate->value => 0.03,
                Sector::BasicMaterials->value => 0.03,
            ],
            'sp500' => [
                Sector::Technology->value => 0.29,
                Sector::Healthcare->value => 0.13,
                Sector::FinancialServices->value => 0.13,
                Sector::CommunicationServices->value => 0.09,
                Sector::ConsumerCyclical->value => 0.10,
                Sector::ConsumerDefensive->value => 0.06,
                Sector::Industrials->value => 0.08,
                Sector::Energy->value => 0.04,
                Sector::Utilities->value => 0.03,
                Sector::RealEstate->value => 0.03,
                Sector::BasicMaterials->value => 0.02,
            ],
            'emerging' => [
                Sector::Technology->value => 0.20,
                Sector::FinancialServices->value => 0.22,
                Sector::CommunicationServices->value => 0.10,
                Sector::ConsumerCyclical->value => 0.13,
                Sector::ConsumerDefensive->value => 0.06,
                Sector::Industrials->value => 0.06,
                Sector::Energy->value => 0.07,
                Sector::Healthcare->value => 0.04,
                Sector::BasicMaterials->value => 0.07,
                Sector::Utilities->value => 0.03,
                Sector::RealEstate->value => 0.02,
            ],
            'europe' => [
                Sector::FinancialServices->value => 0.18,
                Sector::Healthcare->value => 0.15,
                Sector::Industrials->value => 0.16,
                Sector::Technology->value => 0.09,
                Sector::ConsumerCyclical->value => 0.10,
                Sector::ConsumerDefensive->value => 0.10,
                Sector::Energy->value => 0.06,
                Sector::BasicMaterials->value => 0.06,
                Sector::Utilities->value => 0.05,
                Sector::CommunicationServices->value => 0.03,
                Sector::RealEstate->value => 0.02,
            ],
            'nasdaq' => [
                Sector::Technology->value => 0.50,
                Sector::CommunicationServices->value => 0.16,
                Sector::ConsumerCyclical->value => 0.14,
                Sector::Healthcare->value => 0.07,
                Sector::ConsumerDefensive->value => 0.04,
                Sector::Industrials->value => 0.04,
                Sector::FinancialServices->value => 0.02,
                Sector::Utilities->value => 0.01,
                Sector::Energy->value => 0.01,
                Sector::RealEstate->value => 0.01,
            ],
        ];
    }

    public function run(User $user): void
    {
        $startDate = CarbonImmutable::parse('2021-03-01');
        $endDate = CarbonImmutable::now();

        $peaSecurities = $this->createSecurities(self::PEA_ETFS, $startDate);
        $ctoSecurities = $this->createSecurities(self::CTO_ETFS, $startDate);

        $peaWallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'PEA',
        ]);

        $this->seedDcaTransactions($user, $peaWallet, $peaSecurities, $startDate, $endDate, 500.0);

        $peaWallet->fees()->createMany([
            [
                'name' => 'Prélèvements sociaux',
                'value' => 17.2,
                'unit' => CurrencyModificationUnit::Percentage->value,
                'scope' => FeeScope::RealizedGain->value,
            ],
        ]);

        $ctoWallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'CTO',
        ]);

        $this->seedDcaTransactions($user, $ctoWallet, $ctoSecurities, $startDate, $endDate, 300.0, 'Trade Republic');

        $ctoWallet->fees()->createMany([
            [
                'name' => 'Flat Tax (PFU)',
                'value' => 30,
                'unit' => CurrencyModificationUnit::Percentage->value,
                'scope' => FeeScope::RealizedGain->value,
            ],
        ]);
    }

    /**
     * @param  array<string, array{isin: string, ticker: string, name: string}>  $etfs
     * @return array<string, Security>
     */
    private function createSecurities(array $etfs, CarbonImmutable $startDate): array
    {
        $securities = [];

        foreach ($etfs as $key => $etf) {
            $security = Security::firstOrCreate(
                ['isin' => $etf['isin']],
                [
                    'name' => $etf['name'],
                    'ticker' => $etf['ticker'],
                ],
            );

            $securities[$key] = $security;

            if ($security->wasRecentlyCreated) {
                $this->generatePriceHistory($security, $startDate->subMonth());
                $this->generateSectorAllocations($security, $key);
            }
        }

        return $securities;
    }

    private function generatePriceHistory(Security $security, CarbonImmutable $startDate): void
    {
        $config = self::ETF_CONFIGS[$security->ticker];
        $price = $config['base_price'];
        $volatility = $config['volatility'];

        $date = $startDate;
        $today = CarbonImmutable::now();
        $rows = [];

        while ($date->lte($today)) {
            if ($date->isWeekend()) {
                $date = $date->addDay();

                continue;
            }

            $price *= (1 + $this->randomNormal(0.0003, $volatility));
            $price = max($price, 0.01);

            $close = round($price, 4);
            $open = round($close * (1 + $this->randomNormal(0, 0.003)), 4);
            $high = round(max($open, $close) * (1 + abs($this->randomNormal(0, 0.004))), 4);
            $low = round(min($open, $close) * (1 - abs($this->randomNormal(0, 0.004))), 4);

            $rows[] = [
                'security_id' => $security->id,
                'date' => $date->toDateString(),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => rand(100000, 2000000),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $date = $date->addDay();
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SecurityPrice::insert($chunk);
        }
    }

    private function generateSectorAllocations(Security $security, string $etfKey): void
    {
        $allocations = self::sectorAllocations()[$etfKey];
        $rows = [];

        foreach ($allocations as $sector => $weight) {
            $rows[] = [
                'security_id' => $security->id,
                'sector' => $sector,
                'weight' => $weight,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SecuritySector::insert($rows);
    }

    private function randomNormal(float $mean, float $stddev): float
    {
        $u1 = max(mt_rand() / mt_getrandmax(), 1e-10);
        $u2 = mt_rand() / mt_getrandmax();

        return $mean + $stddev * sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    /**
     * @param  array<string, Security>  $securities
     */
    private function seedDcaTransactions(User $user, Wallet $wallet, array $securities, CarbonImmutable $startDate, CarbonImmutable $endDate, float $monthlyBudget, ?string $broker = null): void
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
                    'wallet_id' => $wallet->id,
                    'date' => $investDate->toDateString(),
                    'security_id' => $security->id,
                    'broker' => $broker,
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
}
