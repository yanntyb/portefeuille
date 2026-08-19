<?php

namespace Database\Seeders;

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
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
        $this->seedWalletFees($reader);
        $this->seedAllocationProfiles($reader);
        $this->seedInvitations($reader);
        $this->seedFeedback($reader);
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
     */
    private function seedUsers(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('users') as [$dumpId, , , $role, $verifiedAt, , , $createdAt, $updatedAt]) {
            $isAdmin = $role === Role::Admin->value;
            $email = $isAdmin ? 'admin@example.test' : "utilisateur-{$dumpId}@example.test";

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
     * Colonnes du dump : id, user_id, name, created_at, updated_at.
     */
    private function seedWallets(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('wallets') as [$dumpId, $dumpUserId, $name, $createdAt, $updatedAt]) {
            $userId = $this->users[(int) $dumpUserId] ?? null;

            if ($userId === null) {
                continue;
            }

            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $name],
                ['created_at' => $createdAt, 'updated_at' => $updatedAt],
            );

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
     * unit_price, fees, realized_gain, notes, created_at, updated_at.
     *
     * Une création Eloquent par ligne, et non un `insert()` groupé : c'est `TransactionObserver`
     * qui alimente `holdings_projection`, et il n'écoute que les événements de modèle. Il
     * recalcule aussi `realized_gain`, inutile de reprendre celui du dump.
     */
    private function seedTransactions(MysqlDumpReader $reader): void
    {
        foreach ($reader->rows('transactions') as $row) {
            [, $dumpUserId, $dumpWalletId, $date, $type, $dumpAssetId, $broker, $quantity, $unitPrice, $fees, , $notes, $createdAt, $updatedAt] = $row;

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
                'broker' => $broker,
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
     * Passage par le query builder faute de modèle Eloquent, comme pour les profils
     * d'allocation, les invitations et les feedback.
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

    /**
     * Colonnes du dump : profils — id, name, user_id, wallet_id, created_at, updated_at ;
     * lignes — id, allocation_profile_id, security_id, target_percentage, created_at, updated_at.
     */
    private function seedAllocationProfiles(MysqlDumpReader $reader): void
    {
        $obsoleteIds = DB::table('allocation_profiles')->whereIn('user_id', $this->users)->pluck('id');

        DB::table('allocation_profile_items')->whereIn('allocation_profile_id', $obsoleteIds)->delete();
        DB::table('allocation_profiles')->whereIn('id', $obsoleteIds)->delete();

        /** @var array<int, int> $profiles */
        $profiles = [];

        foreach ($reader->rows('allocation_profiles') as [$dumpId, $name, $dumpUserId, $dumpWalletId, $createdAt, $updatedAt]) {
            $userId = $this->users[(int) $dumpUserId] ?? null;

            if ($userId === null) {
                continue;
            }

            $profiles[(int) $dumpId] = DB::table('allocation_profiles')->insertGetId([
                'name' => $name,
                'user_id' => $userId,
                'wallet_id' => $dumpWalletId === null ? null : ($this->wallets[(int) $dumpWalletId] ?? null),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }

        $rows = [];

        foreach ($reader->rows('allocation_profile_items') as [, $dumpProfileId, $dumpAssetId, $percentage, $createdAt, $updatedAt]) {
            $profileId = $profiles[(int) $dumpProfileId] ?? null;
            $assetId = $this->instruments[(int) $dumpAssetId] ?? null;

            if ($profileId === null || $assetId === null) {
                continue;
            }

            $rows[] = [
                'allocation_profile_id' => $profileId,
                'asset_id' => $assetId,
                'target_percentage' => $percentage,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($rows !== []) {
            DB::table('allocation_profile_items')->insert($rows);
        }
    }

    /**
     * Colonnes du dump : id, token, created_by, expires_at, used_at, created_at, updated_at.
     */
    private function seedInvitations(MysqlDumpReader $reader): void
    {
        DB::table('invitations')->whereIn('created_by', $this->users)->delete();

        $rows = [];

        foreach ($reader->rows('invitations') as [, $token, $dumpCreatedBy, $expiresAt, $usedAt, $createdAt, $updatedAt]) {
            $createdBy = $this->users[(int) $dumpCreatedBy] ?? null;

            if ($createdBy === null) {
                continue;
            }

            $rows[] = [
                'token' => $token,
                'created_by' => $createdBy,
                'expires_at' => $expiresAt,
                'used_at' => $usedAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($rows !== []) {
            DB::table('invitations')->insert($rows);
        }
    }

    /**
     * Colonnes du dump : id, user_id, subject, body, created_at, updated_at.
     */
    private function seedFeedback(MysqlDumpReader $reader): void
    {
        DB::table('feedback')->whereIn('user_id', $this->users)->delete();

        $rows = [];

        foreach ($reader->rows('feedback') as [, $dumpUserId, $subject, $body, $createdAt, $updatedAt]) {
            $userId = $this->users[(int) $dumpUserId] ?? null;

            if ($userId === null) {
                continue;
            }

            $rows[] = [
                'user_id' => $userId,
                'subject' => $subject,
                'body' => $body,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        foreach (array_chunk($rows, self::ROW_CHUNK) as $chunk) {
            DB::table('feedback')->insert($chunk);
        }
    }
}
