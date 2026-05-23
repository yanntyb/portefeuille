<?php

namespace Database\Seeders;

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $asset = Asset::firstOrCreate(
            ['isin' => 'LU0629459743'],
            [
                'name' => 'Amundi PEA S&P 500 UCITS ETF',
                'ticker' => 'AMS',
                'type' => 'etf',
            ],
        );

        // Generate 12 months of price history
        $from = today()->subYear();
        $to = today();

        for ($i = 0; $i < 252; $i++) {
            $date = $from->addDays($i);
            if ($date > $to) {
                break;
            }

            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            AssetPrice::firstOrCreate(
                [
                    'asset_id' => $asset->id,
                    'date' => $date,
                ],
                [
                    'open' => 100 + rand(-20, 20),
                    'high' => 105 + rand(-20, 20),
                    'low' => 95 + rand(-20, 20),
                    'close' => 100 + rand(-20, 20),
                    'volume' => rand(10000, 100000),
                ],
            );
        }
    }
}
