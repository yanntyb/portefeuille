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

abstract class FixedDcaSeeder extends Seeder
{
    protected const YEARS_OF_HISTORY = 5;

    protected const MONTHLY_AMOUNT = 50.0;

    protected const FEE_PER_ORDER = 0.0;

    abstract protected function ticker(): string;

    abstract protected function instrumentName(): string;

    abstract protected function instrumentType(): InstrumentType;

    abstract protected function walletName(): string;

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => $this->walletName(),
        ]);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        $instrument = Instrument::query()->firstOrCreate(
            ['ticker' => $this->ticker()],
            ['name' => $this->instrumentName(), 'isin' => null, 'type' => $this->instrumentType()],
        );

        $start = today()->subYears(static::YEARS_OF_HISTORY)->format('Y-m-d');
        $end = today()->format('Y-m-d');

        $history = app(YahooFinanceAdapter::class)->getPriceHistory($instrument->id, $start, $end)
            ->filter(fn (array $row): bool => ($row['close'] ?? 0) > 0)
            ->sortBy('date')
            ->values();

        if ($history->isEmpty()) {
            $this->command?->warn('Yahoo: pas de données pour '.$this->ticker().', DCA ignoré');

            return;
        }

        $this->storePriceHistory($instrument->id, $history);
        $this->seedFixedDca($user->id, $wallet->id, $instrument->id, $history);
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
     * DCA fixe : chaque mois, MONTHLY_AMOUNT investi au premier cours du mois.
     *
     * @param  Collection<int, array{date: string, close: float}>  $history
     */
    private function seedFixedDca(int $userId, int $walletId, int $assetId, Collection $history): void
    {
        $cursor = Carbon::parse($history->first()['date'])->startOfMonth();
        $lastMonth = today()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $monthStart = $cursor->format('Y-m-d');
            $nextMonth = $cursor->copy()->addMonth()->format('Y-m-d');

            $buyRow = $history->first(
                fn (array $row): bool => $row['date'] >= $monthStart && $row['date'] < $nextMonth,
            );

            if ($buyRow !== null) {
                $close = (float) $buyRow['close'];

                Transaction::query()->create([
                    'user_id' => $userId,
                    'wallet_id' => $walletId,
                    'asset_id' => $assetId,
                    'type' => TransactionType::Buy,
                    'date' => $buyRow['date'],
                    'quantity' => round(static::MONTHLY_AMOUNT / $close, 8),
                    'unit_price' => $close,
                    'fees' => static::FEE_PER_ORDER,
                ]);
            }

            $cursor->addMonth();
        }
    }
}
