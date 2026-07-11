<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Positions de démonstration exprimées en transactions.
     *
     * `close` null = prix indisponible. `sellQty` > 0 ajoute une vente
     * (exerce le calcul du gain réalisé et la réduction de position).
     *
     * @var list<array{name: string, ticker: string, type: InstrumentType, close: float|null, buyQty: float, buyPrice: float, sellQty: float, sellPrice: float}>
     */
    private const POSITIONS = [
        ['name' => 'Apple Inc.', 'ticker' => 'AAPL', 'type' => InstrumentType::Stock, 'close' => 220.0, 'buyQty' => 15, 'buyPrice' => 150.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'NVIDIA Corp.', 'ticker' => 'NVDA', 'type' => InstrumentType::Stock, 'close' => 120.0, 'buyQty' => 50, 'buyPrice' => 140.0, 'sellQty' => 10, 'sellPrice' => 130.0],
        ['name' => 'Amundi PEA S&P 500 UCITS ETF', 'ticker' => 'AMS', 'type' => InstrumentType::ETF, 'close' => 45.0, 'buyQty' => 200, 'buyPrice' => 30.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'Bitcoin', 'ticker' => 'BTC', 'type' => InstrumentType::Crypto, 'close' => 58000.0, 'buyQty' => 0.3, 'buyPrice' => 62000.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'OAT France 2032', 'ticker' => 'OAT32', 'type' => InstrumentType::Bond, 'close' => 98.0, 'buyQty' => 50, 'buyPrice' => 100.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'Ethereum', 'ticker' => 'ETH', 'type' => InstrumentType::Crypto, 'close' => null, 'buyQty' => 2, 'buyPrice' => 3000.0, 'sellQty' => 0, 'sellPrice' => 0.0],
    ];

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Démo',
        ]);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        foreach (self::POSITIONS as $position) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $position['ticker']],
                ['name' => $position['name'], 'type' => $position['type']],
            );

            if ($position['close'] !== null) {
                $this->seedPriceHistory($instrument->id, $position['buyPrice'], $position['close']);
            }

            Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'asset_id' => $instrument->id,
                'type' => TransactionType::Buy,
                'date' => today()->subMonths(11),
                'quantity' => $position['buyQty'],
                'unit_price' => $position['buyPrice'],
                'fees' => 0,
            ]);

            if ($position['sellQty'] > 0) {
                Transaction::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'asset_id' => $instrument->id,
                    'type' => TransactionType::Sell,
                    'date' => today()->subDays(7),
                    'quantity' => $position['sellQty'],
                    'unit_price' => $position['sellPrice'],
                    'fees' => 0,
                ]);
            }
        }
    }

    /**
     * Génère ~12 mois d'historique hebdomadaire, du prix d'achat vers le close actuel,
     * avec une légère ondulation, afin que la courbe de valorisation soit visible.
     */
    private function seedPriceHistory(int $assetId, float $start, float $end): void
    {
        $weeks = 52;
        $from = today()->subWeeks($weeks);

        for ($i = 0; $i <= $weeks; $i++) {
            $progress = $i / $weeks;
            $base = $start + ($end - $start) * $progress;
            $close = round($base * (1 + 0.03 * sin($i / 3.0)), 2);
            $date = $from->copy()->addWeeks($i);

            Price::query()->updateOrCreate(
                ['asset_id' => $assetId, 'date' => $date],
                ['open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 0],
            );
        }

        Price::query()->updateOrCreate(
            ['asset_id' => $assetId, 'date' => today()],
            ['open' => $end, 'high' => $end, 'low' => $end, 'close' => $end, 'volume' => 0],
        );
    }
}
