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
     * @var array{isin: string, ticker: string, name: string}
     */
    private const SP500_ETF = [
        'isin' => 'FR0011871128',
        'ticker' => 'PE500.PA',
        'name' => 'Amundi PEA S&P 500 UCITS ETF',
    ];

    /**
     * @var array{isin: string, ticker: string, name: string}
     */
    private const TOTALENERGIES_STOCK = [
        'isin' => 'FR0000120271',
        'ticker' => 'TTE.PA',
        'name' => 'TotalEnergies SE',
    ];

    /**
     * @return array<string, array<string, float>>
     */
    private static function sectorAllocations(): array
    {
        return [
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
            'totalenergies' => [
                Sector::Energy->value => 1.0,
            ],
        ];
    }

    public function run(User $user): void
    {
        $startDate = CarbonImmutable::now()->subYears(5)->startOfMonth();
        $endDate = CarbonImmutable::now();

        $sp500Securities = $this->createSecurities(['sp500' => self::SP500_ETF]);
        $totalenergiesSecurities = $this->createSecurities(['totalenergies' => self::TOTALENERGIES_STOCK]);

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'PEA',
        ]);

        $this->seedDcaTransactions($user, $wallet, $sp500Securities, $startDate, $endDate, 500.0);
        $this->seedOneTimeBuy($user, $wallet, $totalenergiesSecurities['totalenergies'], CarbonImmutable::parse('2025-01-15'), 2000.0);

        $wallet->fees()->createMany([
            [
                'name' => 'Prélèvements sociaux',
                'value' => 17.2,
                'unit' => CurrencyModificationUnit::Percentage->value,
                'scope' => FeeScope::RealizedGain->value,
            ],
        ]);
    }

    /**
     * @param  array<string, array{isin: string, ticker: string, name: string}>  $stocks
     * @return array<string, Security>
     */
    private function createSecurities(array $stocks): array
    {
        $securities = [];

        foreach ($stocks as $key => $stock) {
            $security = Security::firstOrCreate(
                ['isin' => $stock['isin']],
                [
                    'name' => $stock['name'],
                    'ticker' => $stock['ticker'],
                ],
            );

            $securities[$key] = $security;

            if ($security->wasRecentlyCreated) {
                $this->loadPricesFromFile($security);
                $this->generateSectorAllocations($security, $key);
            }
        }

        return $securities;
    }

    private function loadPricesFromFile(Security $security): void
    {
        $filename = database_path('seeders/data/'.str_replace('.', '_', $security->ticker).'_prices.json');

        if (! file_exists($filename)) {
            return;
        }

        /** @var list<array{date: string, open: float, high: float, low: float, close: float, volume: int}> $prices */
        $prices = json_decode(file_get_contents($filename), true);

        $rows = array_map(fn (array $p) => [
            'security_id' => $security->id,
            'date' => $p['date'],
            'open' => $p['open'],
            'high' => $p['high'],
            'low' => $p['low'],
            'close' => $p['close'],
            'volume' => $p['volume'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $prices);

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

    private function seedOneTimeBuy(User $user, Wallet $wallet, Security $security, CarbonImmutable $date, float $budget, ?string $broker = null): void
    {
        if ($date->isWeekend()) {
            $date = $date->next(CarbonImmutable::MONDAY);
        }

        $price = $this->getPriceAt($security, $date);

        if ($price === null) {
            return;
        }

        $quantity = floor($budget / $price * 10000) / 10000;

        if ($quantity <= 0) {
            return;
        }

        $fees = round($quantity * $price * 0.002, 2);

        Transaction::insert([[
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'date' => $date->toDateString(),
            'type' => 'buy',
            'security_id' => $security->id,
            'broker' => $broker,
            'quantity' => $quantity,
            'unit_price' => round($price, 4),
            'fees' => $fees,
            'realized_gain' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    private function getPriceAt(Security $security, CarbonImmutable $date): ?float
    {
        $price = SecurityPrice::query()
            ->where('security_id', $security->id)
            ->where('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->value('close');

        return $price !== null ? (float) $price : null;
    }

    /**
     * @param  array<string, Security>  $securities
     * @param  array<string, float>|null  $allocations
     */
    private function seedDcaTransactions(User $user, Wallet $wallet, array $securities, CarbonImmutable $startDate, CarbonImmutable $endDate, float $monthlyBudget, ?string $broker = null, int $dayOfMonth = 15, ?array $allocations = null): void
    {
        $date = $startDate;
        $transactions = [];

        while ($date->lte($endDate)) {
            $investDate = $date->day($dayOfMonth);

            if ($investDate->isWeekend()) {
                $investDate = $investDate->next(CarbonImmutable::MONDAY);
            }

            if ($investDate->gt($endDate)) {
                break;
            }

            foreach ($securities as $key => $security) {
                $budget = $allocations !== null
                    ? $monthlyBudget * ($allocations[$key] ?? 0)
                    : $monthlyBudget / count($securities);

                $price = SecurityPrice::query()
                    ->where('security_id', $security->id)
                    ->where('date', '<=', $investDate->toDateString())
                    ->orderByDesc('date')
                    ->value('close');

                if ($price === null) {
                    continue;
                }

                $price = (float) $price;
                $quantity = floor($budget / $price * 10000) / 10000;

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
