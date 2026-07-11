<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class EtfHistorySeeder extends Seeder
{
    /**
     * ETF Amundi (Euronext Paris) dont l'historique est récupéré chez Yahoo.
     *
     * @var list<array{ticker: string, name: string, isin: string}>
     */
    private const ETFS = [
        ['ticker' => 'PE500.PA', 'name' => 'Amundi PEA S&P 500 UCITS ETF', 'isin' => 'FR0011871128'],
        ['ticker' => 'PUST.PA', 'name' => 'Amundi Nasdaq-100 UCITS ETF', 'isin' => 'LU1681038243'],
        ['ticker' => 'MEUD.PA', 'name' => 'Amundi Stoxx Europe 600 UCITS ETF', 'isin' => 'LU0908500753'],
        ['ticker' => 'AEEM.PA', 'name' => 'Amundi MSCI Emerging Markets UCITS ETF', 'isin' => 'LU1681045370'],
    ];

    private const YEARS_OF_HISTORY = 5;

    private const BUYS_PER_ETF = 5;

    private const INVESTMENT_PER_BUY = 500.0;

    private const FEE_PER_ORDER = 1.00;

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Portefeuille ETF',
        ]);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        $yahoo = app(YahooFinanceAdapter::class);
        $start = today()->subYears(self::YEARS_OF_HISTORY)->format('Y-m-d');
        $end = today()->format('Y-m-d');

        foreach (self::ETFS as $etf) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $etf['ticker']],
                ['name' => $etf['name'], 'isin' => $etf['isin'], 'type' => InstrumentType::ETF],
            );

            $history = $yahoo->getPriceHistory($instrument->id, $start, $end)
                ->filter(fn (array $row): bool => ($row['close'] ?? 0) > 0)
                ->sortBy('date')
                ->values();

            if ($history->isEmpty()) {
                $this->command?->warn("Yahoo: pas de données pour {$etf['ticker']}, ignoré");

                continue;
            }

            $this->storePriceHistory($instrument->id, $history);
            $this->seedRandomBuys($user->id, $wallet->id, $instrument->id, $history);
        }
    }

    /**
     * @param  Collection<int, array{date: string, open?: float, high?: float, low?: float, close: float, volume?: int}>  $history
     */
    private function storePriceHistory(int $assetId, Collection $history): void
    {
        $history
            ->map(fn (array $row): array => [
                'asset_id' => $assetId,
                'date' => $row['date'],
                'open' => $row['open'] ?? $row['close'],
                'high' => $row['high'] ?? $row['close'],
                'low' => $row['low'] ?? $row['close'],
                'close' => $row['close'],
                'volume' => $row['volume'] ?? 0,
            ])
            ->chunk(500)
            ->each(fn (Collection $chunk) => Price::query()->upsert(
                $chunk->values()->all(),
                ['asset_id', 'date'],
                ['open', 'high', 'low', 'close', 'volume'],
            ));
    }

    /**
     * @param  Collection<int, array{date: string, close: float}>  $history
     */
    private function seedRandomBuys(int $userId, int $walletId, int $assetId, Collection $history): void
    {
        foreach ($this->pickSpreadDates($history, self::BUYS_PER_ETF) as $row) {
            $close = (float) $row['close'];

            Transaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $walletId,
                'asset_id' => $assetId,
                'type' => TransactionType::Buy,
                'date' => $row['date'],
                'quantity' => round(self::INVESTMENT_PER_BUY / $close, 4),
                'unit_price' => $close,
                'fees' => self::FEE_PER_ORDER,
            ]);
        }
    }

    /**
     * Sélectionne $count lignes réparties sur toute la période (une par tranche),
     * avec une date aléatoire dans chaque tranche.
     *
     * @param  Collection<int, array{date: string, close: float}>  $rows
     * @return Collection<int, array{date: string, close: float}>
     */
    private function pickSpreadDates(Collection $rows, int $count): Collection
    {
        $total = $rows->count();

        if ($total <= $count) {
            return $rows;
        }

        $bucketSize = intdiv($total, $count);
        $picks = collect();

        for ($i = 0; $i < $count; $i++) {
            $from = $i * $bucketSize;
            $to = $i === $count - 1 ? $total - 1 : $from + $bucketSize - 1;
            $picks->push($rows[random_int($from, $to)]);
        }

        return $picks;
    }
}
