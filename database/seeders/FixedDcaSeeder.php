<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Models\Instrument;
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

    public function __construct(private PriceRepositoryContract $prices) {}

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
     * Persiste l'historique via le repository : lui seul écrit la date au format
     * 'Y-m-d H:i:s' attendu par l'index unique (asset_id, date). Écrire ici la date brute
     * 'Y-m-d' du script Python créerait, sous le typage dynamique de SQLite, une seconde
     * ligne pour un jour déjà synchronisé.
     *
     * @param  Collection<int, array{date: string, open?: float, high?: float, low?: float, close: float, volume?: int}>  $history
     */
    private function storePriceHistory(int $assetId, Collection $history): void
    {
        $this->prices->upsertForAsset(
            $assetId,
            $history->map(fn (array $row): PriceData => PriceData::fromArray($row))->all(),
        );
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
