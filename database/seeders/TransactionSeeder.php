<?php

namespace Database\Seeders;

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(User $user): void
    {
        $amundi = Security::factory()->create([
            'isin' => 'FR0011871110',
            'name' => 'Amundi MSCI World UCITS ETF',
            'ticker' => 'PUST.PA',
        ]);

        $bnp = Security::factory()->create([
            'isin' => 'FR0011550193',
            'name' => 'BNP Paribas Easy S&P 500 UCITS ETF',
            'ticker' => 'ETZ.PA',
        ]);

        $chevron = Security::factory()->create([
            'isin' => 'US1667641005',
            'name' => 'Chevron Corporation',
            'ticker' => 'CVX',
        ]);

        $this->seedPriceHistory($amundi, 75.0, 0.0004, 0.010);
        $this->seedPriceHistory($bnp, 17.0, 0.0003, 0.012);
        $this->seedPriceHistory($chevron, 145.0, 0.0003, 0.018);

        $peaTransactions = [
            // juil.-25
            ['date' => '2025-07-15', 'security_id' => $amundi->id, 'quantity' => 1, 'unit_price' => 79.36, 'fees' => 0.28],
            ['date' => '2025-07-15', 'security_id' => $bnp->id, 'quantity' => 2, 'unit_price' => 17.62, 'fees' => 0],
            // août-25
            ['date' => '2025-08-15', 'security_id' => $amundi->id, 'quantity' => 4, 'unit_price' => 82.80, 'fees' => 1.16],
            ['date' => '2025-08-15', 'security_id' => $bnp->id, 'quantity' => 9, 'unit_price' => 17.65, 'fees' => 0.56],
            // sept.-25
            ['date' => '2025-09-15', 'security_id' => $amundi->id, 'quantity' => 4, 'unit_price' => 80.16, 'fees' => 0],
            ['date' => '2025-09-15', 'security_id' => $bnp->id, 'quantity' => 10, 'unit_price' => 17.73, 'fees' => 0.62],
            // oct.-25
            ['date' => '2025-10-15', 'security_id' => $amundi->id, 'quantity' => 4, 'unit_price' => 83.46, 'fees' => 0],
            ['date' => '2025-10-15', 'security_id' => $bnp->id, 'quantity' => 9, 'unit_price' => 18.08, 'fees' => 0.57],
            // nov.-25
            ['date' => '2025-11-17', 'security_id' => $amundi->id, 'quantity' => 3, 'unit_price' => 90.50, 'fees' => 0],
            ['date' => '2025-11-17', 'security_id' => $bnp->id, 'quantity' => 12, 'unit_price' => 18.51, 'fees' => 0.78],
            // déc.-25
            ['date' => '2025-12-15', 'security_id' => $amundi->id, 'quantity' => 4, 'unit_price' => 87.18, 'fees' => 0],
            ['date' => '2025-12-15', 'security_id' => $bnp->id, 'quantity' => 8, 'unit_price' => 18.53, 'fees' => 0.52],
            // janv.-26
            ['date' => '2026-01-15', 'security_id' => $amundi->id, 'quantity' => 3, 'unit_price' => 85.94, 'fees' => 0],
            ['date' => '2026-01-15', 'security_id' => $bnp->id, 'quantity' => 12, 'unit_price' => 19.25, 'fees' => 0.81],
            // févr.-26
            ['date' => '2026-02-15', 'security_id' => $amundi->id, 'quantity' => 4, 'unit_price' => 85.67, 'fees' => 0],
            ['date' => '2026-02-15', 'security_id' => $bnp->id, 'quantity' => 9, 'unit_price' => 19.79, 'fees' => 0.62],
        ];

        foreach ($peaTransactions as $tx) {
            Transaction::factory()->pea()->create([...$tx, 'user_id' => $user->id]);
        }

        // CTO : janv.-26
        Transaction::factory()->cto()->create([
            'user_id' => $user->id,
            'date' => '2026-01-15',
            'security_id' => $chevron->id,
            'quantity' => 1,
            'unit_price' => 155.20,
            'fees' => 50.16,
            'broker' => 'Fortuneo',
        ]);

        // Livret : un versement par mois (août-25 à févr.-26)
        $livretDates = [
            '2025-08-01', '2025-09-01', '2025-10-01',
            '2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01',
        ];

        foreach ($livretDates as $date) {
            Transaction::factory()->livret()->create([
                'user_id' => $user->id,
                'date' => $date,
                'notes' => 'Versement mensuel',
            ]);
        }
    }

    /**
     * Génère un historique de prix avec une marche aléatoire réaliste.
     */
    private function seedPriceHistory(Security $security, float $startPrice, float $dailyDrift, float $volatility): void
    {
        $date = CarbonImmutable::parse('2025-06-01');
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

        SecurityPrice::insert($rows);
    }

    private function gaussianRandom(): float
    {
        $u1 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $u2 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
