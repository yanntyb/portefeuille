<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Positions de démonstration.
     *
     * Chaque entrée : nom, ticker, type, prix de clôture courant (null = prix
     * indisponible), quantité détenue, coût d'achat moyen.
     *
     * @var list<array{name: string, ticker: string, type: string, close: float|null, quantity: float, avgCost: float}>
     */
    private const POSITIONS = [
        ['name' => 'Apple Inc.', 'ticker' => 'AAPL', 'type' => 'stock', 'close' => 220.0, 'quantity' => 15, 'avgCost' => 150.0],
        ['name' => 'NVIDIA Corp.', 'ticker' => 'NVDA', 'type' => 'stock', 'close' => 120.0, 'quantity' => 40, 'avgCost' => 140.0],
        ['name' => 'Amundi PEA S&P 500 UCITS ETF', 'ticker' => 'AMS', 'type' => 'etf', 'close' => 45.0, 'quantity' => 200, 'avgCost' => 30.0],
        ['name' => 'Bitcoin', 'ticker' => 'BTC', 'type' => 'crypto', 'close' => 58000.0, 'quantity' => 0.3, 'avgCost' => 62000.0],
        ['name' => 'OAT France 2032', 'ticker' => 'OAT32', 'type' => 'bond', 'close' => 98.0, 'quantity' => 50, 'avgCost' => 100.0],
        ['name' => 'Ethereum', 'ticker' => 'ETH', 'type' => 'crypto', 'close' => null, 'quantity' => 2, 'avgCost' => 3000.0],
    ];

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Démo',
        ]);

        foreach (self::POSITIONS as $position) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $position['ticker']],
                ['name' => $position['name'], 'type' => $position['type']],
            );

            if ($position['close'] !== null) {
                Price::query()->updateOrCreate(
                    ['asset_id' => $instrument->id, 'date' => today()],
                    [
                        'open' => $position['close'],
                        'high' => $position['close'],
                        'low' => $position['close'],
                        'close' => $position['close'],
                        'volume' => 0,
                    ],
                );
            }

            Holding::query()->updateOrCreate(
                ['asset_id' => $instrument->id, 'wallet_id' => $wallet->id],
                [
                    'user_id' => $user->id,
                    'quantity' => $position['quantity'],
                    'avg_cost' => $position['avgCost'],
                ],
            );
        }
    }
}
