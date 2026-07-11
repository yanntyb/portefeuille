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
use Illuminate\Support\Carbon;
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

    private const MONTHLY_BUDGET = 1000.0;

    private const LOOKBACK_MONTHS = 3;

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

        /** @var list<array{instrument: Instrument, history: Collection<int, array{date: string, close: float}>}> $portfolio */
        $portfolio = [];

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
            $portfolio[] = ['instrument' => $instrument, 'history' => $history];
        }

        if ($portfolio === []) {
            return;
        }

        $this->seedMomentumDca($user->id, $wallet->id, $portfolio);
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
     * DCA mensuel : chaque mois, 1000 € investis sur l'ETF au plus fort momentum
     * (rendement sur LOOKBACK_MONTHS). Aucun achat si aucun momentum n'est positif.
     *
     * @param  list<array{instrument: Instrument, history: Collection<int, array{date: string, close: float}>}>  $portfolio
     */
    private function seedMomentumDca(int $userId, int $walletId, array $portfolio): void
    {
        $firstDate = collect($portfolio)
            ->map(fn (array $p): string => $p['history']->first()['date'])
            ->min();

        $cursor = Carbon::parse($firstDate)->startOfMonth();
        $lastMonth = today()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $monthStart = $cursor->format('Y-m-d');
            $nextMonth = $cursor->copy()->addMonth()->format('Y-m-d');

            /** @var array<int, float> $returns */
            $returns = [];
            /** @var array<int, array{date: string, close: float}> $buyRows */
            $buyRows = [];

            foreach ($portfolio as $p) {
                $buyRow = $p['history']->first(
                    fn (array $row): bool => $row['date'] >= $monthStart && $row['date'] < $nextMonth,
                );

                if ($buyRow === null) {
                    continue;
                }

                $refDate = Carbon::parse($buyRow['date'])->subMonths(self::LOOKBACK_MONTHS)->format('Y-m-d');
                $refClose = $this->closeOnOrBefore($p['history'], $refDate);

                if ($refClose === null || $refClose <= 0) {
                    continue;
                }

                $assetId = $p['instrument']->id;
                $returns[$assetId] = (float) $buyRow['close'] / $refClose - 1;
                $buyRows[$assetId] = $buyRow;
            }

            $winnerId = self::selectWinner($returns);

            if ($winnerId !== null) {
                $this->recordBuy($userId, $walletId, $winnerId, $buyRows[$winnerId]);
            }

            $cursor->addMonth();
        }
    }

    /**
     * @param  array{date: string, close: float}  $row
     */
    private function recordBuy(int $userId, int $walletId, int $assetId, array $row): void
    {
        $close = (float) $row['close'];

        Transaction::query()->create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'asset_id' => $assetId,
            'type' => TransactionType::Buy,
            'date' => $row['date'],
            'quantity' => round(self::MONTHLY_BUDGET / $close, 4),
            'unit_price' => $close,
            'fees' => self::FEE_PER_ORDER,
        ]);
    }

    /**
     * ETF gagnant du mois : plus fort momentum, à condition qu'il soit positif.
     *
     * @param  array<int, float>  $returns  assetId => momentum
     */
    public static function selectWinner(array $returns): ?int
    {
        if ($returns === []) {
            return null;
        }

        arsort($returns);
        $bestId = array_key_first($returns);

        return $returns[$bestId] > 0 ? $bestId : null;
    }

    /**
     * Dernier close à une date <= $date dans une série triée croissant, sinon null.
     *
     * @param  Collection<int, array{date: string, close: float}>  $history
     */
    private function closeOnOrBefore(Collection $history, string $date): ?float
    {
        $row = $history->last(fn (array $r): bool => $r['date'] <= $date);

        return $row !== null ? (float) $row['close'] : null;
    }
}
