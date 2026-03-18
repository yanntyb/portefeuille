<?php

namespace Database\Seeders;

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\SecuritySector;
use App\Services\YahooFinanceClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class NuclearSecuritiesSeeder extends Seeder
{
    /**
     * @var list<array{isin: string, name: string, ticker: string}>
     */
    private const SECURITIES = [
        ['isin' => 'CA13321L1085', 'name' => 'Cameco Corporation', 'ticker' => 'CCJ'],
        ['isin' => 'US21037T1097', 'name' => 'Constellation Energy', 'ticker' => 'CEG'],
        ['isin' => 'US63253R2013', 'name' => 'Kazatomprom', 'ticker' => 'KAP'],
    ];

    public function run(): void
    {
        $client = app(YahooFinanceClient::class);
        $startDate = CarbonImmutable::now()->subYear()->toDateString();
        $endDate = CarbonImmutable::now()->toDateString();

        foreach (self::SECURITIES as $data) {
            $security = Security::firstOrCreate(
                ['isin' => $data['isin']],
                [
                    'name' => $data['name'],
                    'ticker' => $data['ticker'],
                ],
            );

            $hasPrices = $security->prices()->exists();
            $hasSectors = $security->sectors()->exists();

            if ($hasPrices && $hasSectors) {
                $this->command->info("Security {$data['ticker']} already complete, skipping.");

                continue;
            }

            if (! $hasPrices) {
                $this->command->info("Fetching prices for {$data['ticker']}...");
                $this->fetchAndStorePrices($client, $security, $startDate, $endDate);
            }

            if (! $hasSectors) {
                $this->command->info("Fetching sectors for {$data['ticker']}...");
                $this->fetchAndStoreSectors($client, $security);
            }
        }
    }

    private function fetchAndStorePrices(YahooFinanceClient $client, Security $security, string $startDate, string $endDate): void
    {
        $prices = $client->fetchPrices($security->ticker, $startDate, $endDate);

        if ($prices === []) {
            $this->command->warn("No prices returned for {$security->ticker}.");

            return;
        }

        $rows = array_map(fn (array $price) => [
            'security_id' => $security->id,
            'date' => $price['date'],
            'open' => $price['open'],
            'high' => $price['high'],
            'low' => $price['low'],
            'close' => $price['close'],
            'volume' => $price['volume'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $prices);

        foreach (array_chunk($rows, 500) as $chunk) {
            SecurityPrice::insert($chunk);
        }

        $this->command->info('Stored '.count($prices)." price records for {$security->ticker}.");
    }

    private function fetchAndStoreSectors(YahooFinanceClient $client, Security $security): void
    {
        $sectors = $client->fetchSectors($security->ticker);

        if ($sectors === []) {
            $this->command->warn("No sectors returned for {$security->ticker}.");

            return;
        }

        $rows = [];
        foreach ($sectors as $sector => $weight) {
            $rows[] = [
                'security_id' => $security->id,
                'sector' => $sector,
                'weight' => $weight,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        SecuritySector::insert($rows);

        $this->command->info('Stored '.count($rows)." sector(s) for {$security->ticker}.");
    }
}
