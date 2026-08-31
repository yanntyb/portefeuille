<?php

namespace Database\Seeders;

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Database\Seeders\Support\MysqlDumpReader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rejoue le snapshot de production `storage/database/backup.sql` sur le schéma actuel.
 *
 * Alternative aux seeders de démonstration, qui reconstruisent des données synthétiques : ici
 * les identités, portefeuilles et transactions viennent du dump, donc un historique crédible.
 * À lancer explicitement, il n'est pas branché sur `DatabaseSeeder` :
 * `php artisan db:seed --class=BackupSeeder`.
 *
 * Prix et secteurs, en revanche, ne sont pas rejoués : les cotations du dump sont figées à la
 * date du snapshot. Ils sont resynchronisés en fin de course par `market:sync-prices` et
 * `market:sync-sectors`, seule source à jour.
 *
 * Les identités du dump sont réelles ; noms, emails et mots de passe sont donc régénérés.
 * Les identifiants non plus ne sont pas repris : `InstrumentCatalogSeeder` peuple déjà la table
 * `assets`, forcer les ids du dump provoquerait des collisions. Chaque table du dump est donc
 * associée à sa contrepartie par clé métier (email, ticker, couple utilisateur + nom de
 * portefeuille) et les correspondances `id du dump => id en base` sont mémorisées au fil de l'eau.
 */
class BackupSeeder extends Seeder
{
    use SyncsMarketData;

    /**
     * Tickers du dump correspondant à des actions ; tout le reste est un ETF ou un fonds.
     *
     * Le dump précède l'ajout de `assets.type`, la colonne doit être déduite.
     *
     * @var list<string>
     */
    private const STOCK_TICKERS = ['CVX', 'AI.PA', 'TTE.PA', 'HAG.DE', 'NVDA', 'MSFT', 'AMZN', 'TSLA'];

    /**
     * Enveloppe de chaque nom de portefeuille du dump, qui précède la colonne `account_type`.
     *
     * Même principe que `STOCK_TICKERS` pour `assets.type` : la colonne se déduit, la
     * correspondance est écrite ici. Tout nom inconnu retombe sur le compte-titres — l'enveloppe
     * la moins affirmative : typer à tort en PEA lèverait des alertes d'éligibilité fausses,
     * l'inverse n'affirme rien.
     *
     * @var array<string, string>
     */
    private const WALLET_ACCOUNT_TYPES = [
        'PEA' => 'pea',
        'CTO' => 'cto',
    ];

    /**
     * Établissement qui tient chaque portefeuille de titres.
     *
     * Le dump porte un courtier par transaction — presque toujours vide, et nommant ailleurs
     * celui d'où la ligne avait été importée à l'époque. La base n'a plus cette colonne : le
     * compte est tenu par un établissement, l'ordre n'en nomme aucun. Les deux portefeuilles de
     * titres sont chez IBKR. Un portefeuille absent de cette table n'a pas de courtier.
     *
     * @var array<string, string>
     */
    private const WALLET_BROKERS = [
        'PEA' => 'IBKR',
        'CTO' => 'IBKR',
    ];

    /**
     * Compte administrateur du dump.
     */
    private const ADMIN_EMAIL = 'admin@example.test';

    /**
     * Identifiant, dans le dump, du compte que l'application ouvre par défaut.
     *
     * Faute d'authentification, les contrôleurs retombent sur le premier utilisateur en base
     * (`auth()->user() ?? User::query()->first()`). C'est le compte administrateur du dump :
     * celui qui porte le PEA et le CTO suivis dans la durée, le seul dont les séries d'évolution
     * racontent quelque chose. Il est donc soit greffé sur le premier compte déjà en base
     * (cf. `seedUsers()`), soit inséré avant les autres.
     */
    private const DEFAULT_DUMP_USER_ID = 1;

    /**
     * Achats crypto du compte par défaut, relevés sur l'historique d'ordres Kraken.
     *
     * Ils ne sont pas dans le dump, qui précède l'ouverture du portefeuille crypto. L'ordre
     * annulé du 18 mai 2026 n'est pas repris : `transactions` n'a pas de colonne de statut, un
     * ordre annulé y deviendrait un achat réel et gonflerait la position.
     *
     * Les frais des premiers ordres ne figurent pas sur les relevés conservés, ils restent à zéro.
     * Ceux repris ici gardent la précision du relevé Kraken ; `transactions.fees` n'ayant que deux
     * décimales, ils sont arrondis à l'insertion.
     *
     * @var list<array{date: string, ticker: string, quantity: string, unit_price: string, fees: string}>
     */
    private const CRYPTO_ORDERS = [
        ['date' => '2026-05-18', 'ticker' => 'BTC-EUR', 'quantity' => '0.00075430', 'unit_price' => '66020.6000', 'fees' => '0'],
        ['date' => '2026-06-01', 'ticker' => 'BTC-EUR', 'quantity' => '0.00081870', 'unit_price' => '61070.1000', 'fees' => '0'],
        ['date' => '2026-07-01', 'ticker' => 'BTC-EUR', 'quantity' => '0.00097200', 'unit_price' => '51439.4000', 'fees' => '0'],
        ['date' => '2026-07-31', 'ticker' => 'BTC-EUR', 'quantity' => '0.00090400', 'unit_price' => '55312.0000', 'fees' => '0'],
        ['date' => '2026-08-31', 'ticker' => 'BTC-EUR', 'quantity' => '0.00073812', 'unit_price' => '67769.9000', 'fees' => '0.2001'],
        ['date' => '2026-08-31', 'ticker' => 'ETH-EUR', 'quantity' => '0.02343151', 'unit_price' => '2125.7600', 'fees' => '0.3985'],
    ];

    /**
     * Nom de chaque crypto achetée, aucune ne figurant dans le dump.
     *
     * @var array<string, string>
     */
    private const CRYPTO_NAMES = [
        'BTC-EUR' => 'Bitcoin',
        'ETH-EUR' => 'Ethereum',
    ];

    private const CRYPTO_WALLET = 'Portefeuille Crypto';

    private const CRYPTO_BROKER = 'Kraken';

    /** Kraken détient les clés pour son client : c'est un portefeuille chaud, pas un compte-titres. */
    private const CRYPTO_ACCOUNT_TYPE = AccountType::CryptoHotWallet;

    private const ROW_CHUNK = 100;

    /**
     * Correspondances `id du dump => id en base`.
     *
     * @var array<int, int>
     */
    private array $users = [];

    /** @var array<int, int> */
    private array $wallets = [];

    /** @var array<int, int> */
    private array $instruments = [];

    /**
     * Jour de la transaction la plus ancienne rejouée, point de départ de l'historique de prix.
     */
    private ?string $earliestTransactionDate = null;

    public function run(): void
    {
        if (! is_file($this->dumpPath())) {
            $this->command?->warn('Dump introuvable : '.$this->dumpPath().', BackupSeeder ignoré');

            return;
        }

        $reader = new MysqlDumpReader($this->dumpPath());

        $this->seedUsers($reader);
        $this->seedWallets($reader);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->whereIn('wallet_id', $this->wallets)->delete();
        Holding::query()->whereIn('wallet_id', $this->wallets)->delete();

        $this->seedInstruments($reader);
        $this->seedTransactions($reader);
        $this->seedCryptoOrders();
        $this->seedWalletFees($reader);
        $this->syncPricesAndSectors();
    }

    /**
     * Emplacement du dump, surchargeable par les tests pour viser un extrait réduit.
     */
    protected function dumpPath(): string
    {
        return storage_path('database/backup.sql');
    }

    /**
     * Colonnes du dump : id, name, email, role, email_verified_at, password, remember_token,
     * created_at, updated_at.
     *
     * Nom, email et mot de passe sont remplacés : le dump contient de vraies identités et de
     * vrais hashs. Tous les comptes partagent le mot de passe « password ».
     *
     * Le compte par défaut du dump se greffe sur le premier compte déjà en base quand il y en a
     * un — typiquement celui de `DatabaseSeeder` après un `migrate:fresh --seed`. Sans cela le
     * dump s'insérerait derrière lui et l'application ouvrirait un portefeuille de démonstration
     * plutôt que l'historique réel.
     */
    private function seedUsers(MysqlDumpReader $reader): void
    {
        $hostUser = User::query()->orderBy('id')->first();

        foreach ($this->userRowsDefaultFirst($reader) as [$dumpId, , , $role, $verifiedAt, , , $createdAt, $updatedAt]) {
            if ($hostUser !== null && (int) $dumpId === self::DEFAULT_DUMP_USER_ID) {
                $this->users[(int) $dumpId] = $hostUser->id;

                continue;
            }

            $isAdmin = $role === Role::Admin->value;
            $email = $isAdmin ? self::ADMIN_EMAIL : "utilisateur-{$dumpId}@example.test";

            $user = User::query()->firstOrNew(['email' => $email]);

            // forceFill : les timestamps du dump ne sont pas dans le `$fillable` du modèle.
            $user->forceFill([
                'name' => $isAdmin ? 'Administrateur' : "Utilisateur {$dumpId}",
                'password' => 'password',
                'role' => $role,
                'email_verified_at' => $verifiedAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ])->save();

            $this->users[(int) $dumpId] = $user->id;
        }
    }

    /**
     * Lignes `users` du dump, le compte par défaut en tête.
     *
     * L'ordre d'insertion décide de l'ordre des identifiants en base, donc du compte ouvert par
     * l'application. Les trois lignes de la table tiennent en mémoire sans effort, contrairement
     * aux prix et aux transactions qui restent lus au fil de l'eau.
     *
     * @return list<list<string|null>>
     */
    private function userRowsDefaultFirst(MysqlDumpReader $reader): array
    {
        $rows = iterator_to_array($reader->rows('users'), preserve_keys: false);

        // Tri stable depuis PHP 8.0 : l'ordre du dump est conservé derrière le compte par défaut.
        usort($rows, fn (array $left, array $right): int => $this->insertionRank($left) <=> $this->insertionRank($right));

        return $rows;
    }

    /**
     * Rang d'insertion d'une ligne `users` : 0 pour le compte par défaut, 1 pour les autres.
     *
     * @param  list<string|null>  $row
     */
    private function insertionRank(array $row): int
    {
        return (int) $row[0] === self::DEFAULT_DUMP_USER_ID ? 0 : 1;
    }

    /**
     * Colonnes du dump : id, user_id, name, created_at, updated_at.
     */
    private function seedWallets(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('wallets') as [$dumpId, $dumpUserId, $name, $createdAt, $updatedAt]) {
            $userId = $this->users[(int) $dumpUserId] ?? null;

            if ($userId === null) {
                continue;
            }

            $accountType = self::WALLET_ACCOUNT_TYPES[$name] ?? AccountType::Cto->value;

            $broker = self::WALLET_BROKERS[$name] ?? null;

            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $name],
                [
                    'account_type' => $accountType,
                    'broker' => $broker,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );

            // Rejeu : une ligne déjà créée garde son type et son courtier d'alors — le défaut de
            // colonne pour l'un, rien pour l'autre — tant qu'elle n'est pas remise à jour ici.
            if ($wallet->account_type->value !== $accountType || $wallet->broker !== $broker) {
                $wallet->fill(['account_type' => $accountType, 'broker' => $broker])->save();
            }

            $this->wallets[(int) $dumpId] = $wallet->id;
        }
    }

    /**
     * Colonnes du dump : id, isin, ticker, name, created_at, updated_at.
     */
    private function seedInstruments(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('securities') as [$dumpId, $isin, $ticker, $name, $createdAt, $updatedAt]) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $ticker],
                [
                    'isin' => $isin,
                    'name' => $name,
                    'type' => in_array($ticker, self::STOCK_TICKERS, true)
                        ? InstrumentType::Stock
                        : InstrumentType::ETF,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ],
            );

            $this->instruments[(int) $dumpId] = $instrument->id;
        }
    }

    /**
     * Colonnes du dump : id, user_id, wallet_id, date, type, security_id, broker, quantity,
     * unit_price, fees, realized_gain, notes, created_at, updated_at. Le courtier du dump est
     * ignoré : il se porte désormais sur le portefeuille (cf. `WALLET_BROKERS`).
     *
     * Une création Eloquent par ligne, et non un `insert()` groupé : c'est `TransactionObserver`
     * qui alimente `holdings_projection`, et il n'écoute que les événements de modèle. Il
     * recalcule aussi `realized_gain`, inutile de reprendre celui du dump.
     */
    private function seedTransactions(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('transactions') as $row) {
            [, $dumpUserId, $dumpWalletId, $date, $type, $dumpAssetId, , $quantity, $unitPrice, $fees, , $notes, $createdAt, $updatedAt] = $row;

            $walletId = $this->wallets[(int) $dumpWalletId] ?? null;

            if ($walletId === null) {
                continue;
            }

            $day = substr((string) $date, 0, 10);

            if ($this->earliestTransactionDate === null || $day < $this->earliestTransactionDate) {
                $this->earliestTransactionDate = $day;
            }

            Transaction::query()->create([
                'user_id' => $dumpUserId === null ? null : ($this->users[(int) $dumpUserId] ?? null),
                'wallet_id' => $walletId,
                'asset_id' => $dumpAssetId === null ? null : ($this->instruments[(int) $dumpAssetId] ?? null),
                'date' => $date,
                'type' => $type,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'fees' => $fees,
                'notes' => $notes,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    /**
     * Rejoue les achats crypto du compte par défaut dans un portefeuille dédié.
     *
     * Instruments et portefeuille sont créés au besoin : ni les cryptos ni ce portefeuille ne
     * figurent dans le dump. Une création Eloquent par ordre, comme pour les transactions du
     * dump, sinon `TransactionObserver` n'alimenterait pas `holdings_projection`.
     */
    private function seedCryptoOrders(): void
    {
        $userId = $this->users[self::DEFAULT_DUMP_USER_ID] ?? null;

        if ($userId === null) {
            return;
        }

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $userId, 'name' => self::CRYPTO_WALLET],
            ['broker' => self::CRYPTO_BROKER, 'account_type' => self::CRYPTO_ACCOUNT_TYPE],
        );

        // Rejeu : un portefeuille déjà créé garde son courtier et son type d'alors sans reprise.
        if ($wallet->broker !== self::CRYPTO_BROKER || $wallet->account_type !== self::CRYPTO_ACCOUNT_TYPE) {
            $wallet->fill([
                'broker' => self::CRYPTO_BROKER,
                'account_type' => self::CRYPTO_ACCOUNT_TYPE,
            ])->save();
        }

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        foreach (self::CRYPTO_ORDERS as $order) {
            if ($this->earliestTransactionDate === null || $order['date'] < $this->earliestTransactionDate) {
                $this->earliestTransactionDate = $order['date'];
            }

            Transaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'asset_id' => $this->cryptoInstrument($order['ticker'])->id,
                'date' => $order['date'],
                'type' => TransactionType::Buy,
                'quantity' => $order['quantity'],
                'unit_price' => $order['unit_price'],
                'fees' => $order['fees'],
            ]);
        }
    }

    /**
     * Instrument crypto correspondant au ticker, créé au besoin.
     */
    private function cryptoInstrument(string $ticker): Instrument
    {
        return Instrument::query()->firstOrCreate(
            ['ticker' => $ticker],
            ['isin' => null, 'name' => self::CRYPTO_NAMES[$ticker], 'type' => InstrumentType::Crypto],
        );
    }

    /**
     * Récupère prix et secteurs auprès du fournisseur de marché, plutôt que de rejouer ceux du
     * dump, qui sont figés à la date du snapshot.
     *
     * L'historique démarre à la transaction la plus ancienne : avant elle, aucune valorisation
     * à afficher. Sans transaction rejouée, il n'y a rien à valoriser, la synchronisation est
     * inutile.
     */
    private function syncPricesAndSectors(): void
    {
        if ($this->earliestTransactionDate === null) {
            return;
        }

        $this->syncAllMarketData($this->earliestTransactionDate);
    }

    /**
     * Colonnes du dump : id, wallet_id, name, value, unit, scope, frequency, created_at,
     * updated_at.
     *
     * Passage par le query builder faute de modèle Eloquent.
     */
    private function seedWalletFees(MysqlDumpReader $reader): void
    {
        DB::table('wallet_fees')->whereIn('wallet_id', $this->wallets)->delete();

        $rows = [];

        foreach ($reader->rows('wallet_fees') as [, $dumpWalletId, $name, $value, $unit, $scope, $frequency, $createdAt, $updatedAt]) {
            $walletId = $this->wallets[(int) $dumpWalletId] ?? null;

            if ($walletId === null) {
                continue;
            }

            $rows[] = [
                'wallet_id' => $walletId,
                'name' => $name,
                'value' => $value,
                'unit' => $unit,
                'scope' => $scope,
                'frequency' => $frequency,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($rows !== []) {
            DB::table('wallet_fees')->insert($rows);
        }
    }
}
