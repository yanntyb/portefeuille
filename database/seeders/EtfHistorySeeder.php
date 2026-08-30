<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EtfHistorySeeder extends Seeder
{
    use SyncsMarketData;

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

    /** Plus-value latente à partir de laquelle une ligne est écrêtée. */
    private const PROFIT_TAKING_THRESHOLD = 0.30;

    /** Part de la quantité détenue vendue lors d'une prise de profit. */
    private const PROFIT_TAKING_FRACTION = 0.30;

    /** Décimales des quantités, achat comme vente. */
    private const QUANTITY_PRECISION = 4;

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

        $start = today()->subYears(self::YEARS_OF_HISTORY)->format('Y-m-d');

        /** @var list<array{instrument: Instrument, history: Collection<int, array{date: string, close: float}>}> $portfolio */
        $portfolio = [];

        foreach (self::ETFS as $etf) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $etf['ticker']],
                ['name' => $etf['name'], 'isin' => $etf['isin'], 'type' => InstrumentType::ETF],
            );

            $history = $this->syncMarketData($instrument->id, $start);

            if ($history->isEmpty()) {
                $this->command?->warn("Yahoo: pas de données pour {$etf['ticker']}, ignoré");

                continue;
            }

            $portfolio[] = ['instrument' => $instrument, 'history' => $history];
        }

        if ($portfolio === []) {
            return;
        }

        $this->seedMomentumDca($user->id, $wallet->id, $portfolio);
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

        /**
         * Registre des lignes détenues, tenu au fil des mois : la vente a besoin du prix de
         * revient, que la base ne porte pas encore au moment où le seeder écrit les transactions.
         *
         * @var array<int, array{quantity: float, cost: float}> $lines assetId => ligne
         */
        $lines = [];

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
                $this->recordBuy($userId, $walletId, $winnerId, $buyRows[$winnerId], $lines);
            }

            $this->takeProfits($userId, $walletId, $buyRows, $lines);

            $cursor->addMonth();
        }
    }

    /**
     * @param  array{date: string, close: float}  $row
     * @param  array<int, array{quantity: float, cost: float}>  $lines
     */
    private function recordBuy(int $userId, int $walletId, int $assetId, array $row, array &$lines): void
    {
        $close = (float) $row['close'];
        $quantity = round(self::MONTHLY_BUDGET / $close, self::QUANTITY_PRECISION);

        Transaction::query()->create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'asset_id' => $assetId,
            'type' => TransactionType::Buy,
            'date' => $row['date'],
            'quantity' => $quantity,
            'unit_price' => $close,
            'fees' => self::FEE_PER_ORDER,
        ]);

        $line = $lines[$assetId] ?? ['quantity' => 0.0, 'cost' => 0.0];
        $held = $line['quantity'] + $quantity;

        $lines[$assetId] = [
            'quantity' => $held,
            'cost' => ($line['quantity'] * $line['cost'] + $quantity * $close) / $held,
        ];
    }

    /**
     * Écrête chaque ligne dont la plus-value latente dépasse le seuil, au cours du mois. Le prix
     * de revient ne bouge pas : une vente ne change que la quantité restante.
     *
     * @param  array<int, array{date: string, close: float}>  $rows  assetId => cours du mois
     * @param  array<int, array{quantity: float, cost: float}>  $lines
     */
    private function takeProfits(int $userId, int $walletId, array $rows, array &$lines): void
    {
        foreach ($lines as $assetId => $line) {
            $row = $rows[$assetId] ?? null;

            if ($row === null) {
                continue;
            }

            $close = (float) $row['close'];
            $quantity = self::profitTakingQuantity($line['quantity'], $line['cost'], $close);

            if ($quantity === null) {
                continue;
            }

            Transaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $walletId,
                'asset_id' => $assetId,
                'type' => TransactionType::Sell,
                'date' => $row['date'],
                'quantity' => $quantity,
                'unit_price' => $close,
                'fees' => self::FEE_PER_ORDER,
            ]);

            $lines[$assetId]['quantity'] = round($line['quantity'] - $quantity, self::QUANTITY_PRECISION);
        }
    }

    /**
     * Quantité à vendre sur une ligne, ou null quand il n'y a rien à écrêter : ligne vide, sans
     * prix de revient, sous le seuil de plus-value, ou fraction qui s'annule à l'arrondi.
     */
    public static function profitTakingQuantity(float $held, float $cost, float $close): ?float
    {
        if ($held <= 0 || $cost <= 0) {
            return null;
        }

        // Comparé au cours seuil et non au ratio : 130/100 - 1 vaut 0,30000000000000004 en flottant.
        if ($close <= $cost * (1 + self::PROFIT_TAKING_THRESHOLD)) {
            return null;
        }

        $quantity = round($held * self::PROFIT_TAKING_FRACTION, self::QUANTITY_PRECISION);

        return $quantity > 0 ? $quantity : null;
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
