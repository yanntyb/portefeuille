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
use Illuminate\Support\Facades\Artisan;

/**
 * Portefeuille de démonstration pour la section « Dividendes » de la fiche instrument.
 *
 * Deux valeurs qui en distribuent réellement, achetées assez tôt pour qu'un historique Yahoo de
 * plusieurs années couvre à la fois plus et moins de douze mois : le total perçu, le perçu à
 * douze mois et le rendement sur coût se distinguent alors à l'écran, plutôt que de coïncider
 * comme ils le feraient sur une position récente.
 */
class DividendDemoSeeder extends Seeder
{
    use SyncsMarketData;

    private const WALLET_NAME = 'Portefeuille Dividendes';

    private const YEARS_OF_HISTORY = 3;

    /**
     * @var list<array{ticker: string, name: string, buyQty: float, buyPrice: float}>
     */
    private const STOCKS = [
        ['ticker' => 'AAPL', 'name' => 'Apple Inc.', 'buyQty' => 20, 'buyPrice' => 150.0],
        ['ticker' => 'JNJ', 'name' => 'Johnson & Johnson', 'buyQty' => 15, 'buyPrice' => 140.0],
    ];

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => self::WALLET_NAME,
        ]);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        $since = today()->subYears(self::YEARS_OF_HISTORY);

        /** Achetée un mois avant le début de la fenêtre synchronisée, pour qu'aucun détachement récupéré ne tombe le jour même de l'achat. */
        $boughtAt = $since->copy()->subMonth();

        foreach (self::STOCKS as $stock) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $stock['ticker']],
                ['name' => $stock['name'], 'isin' => null, 'type' => InstrumentType::Stock],
            );

            $history = $this->syncMarketData($instrument->id, $since->format('Y-m-d'));

            if ($history->isEmpty()) {
                $this->command?->warn('Yahoo : pas de cours pour '.$stock['ticker'].', dividendes ignorés');

                continue;
            }

            Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'asset_id' => $instrument->id,
                'type' => TransactionType::Buy,
                'date' => $boughtAt->format('Y-m-d'),
                'quantity' => $stock['buyQty'],
                'unit_price' => $stock['buyPrice'],
                'fees' => 0,
            ]);

            /**
             * Détachements réels de l'instrument, comme les cours ci-dessus : le fournisseur de
             * marché sert la même donnée qu'en production plutôt qu'un montant inventé.
             */
            Artisan::call('market:sync-dividends', [
                '--asset' => $instrument->id,
                '--since' => $boughtAt->format('Y-m-d'),
            ]);
        }
    }
}
