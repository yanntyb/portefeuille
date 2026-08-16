<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MissingBackupSeeder;
use Tests\Fixtures\SampleBackupSeeder;

it('replaces the identities of the dump', function () {
    $this->seed(SampleBackupSeeder::class);

    $users = User::query()->whereIn('email', ['admin@example.test', 'utilisateur-2@example.test'])
        ->orderBy('email')
        ->get();

    expect($users)->toHaveCount(2)
        ->and($users->pluck('name')->all())->toBe(['Administrateur', 'Utilisateur 2'])
        ->and($users->firstWhere('email', 'admin@example.test')->role)->toBe(Role::Admin)
        ->and($users->firstWhere('email', 'utilisateur-2@example.test')->role)->toBe(Role::User)
        ->and(User::query()->where('email', 'like', '%exemple-reel.fr')->exists())->toBeFalse();
});

it('lets every seeded account sign in with the shared password', function () {
    $this->seed(SampleBackupSeeder::class);

    expect(auth()->attempt(['email' => 'admin@example.test', 'password' => 'password']))->toBeTrue();
});

it('rebuilds the wallets of each user', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();

    expect(Wallet::query()->count())->toBe(3)
        ->and(Wallet::query()->where('user_id', $admin->id)->pluck('name')->sort()->values()->all())
        ->toBe(['CTO', 'PEA']);
});

it('infers the instrument type absent from the dump', function () {
    $this->seed(SampleBackupSeeder::class);

    $types = Instrument::query()->pluck('type', 'ticker');

    expect(Instrument::query()->count())->toBe(3)
        ->and($types['PUST.PA'])->toBe(InstrumentType::ETF)
        ->and($types['CVX'])->toBe(InstrumentType::Stock)
        ->and($types['NVDA'])->toBe(InstrumentType::Stock)
        ->and(Instrument::query()->where('ticker', 'CVX')->value('isin'))->toBe('US1667641005');
});

it('restores the sector weights', function () {
    $this->seed(SampleBackupSeeder::class);

    $chevron = Instrument::query()->where('ticker', 'CVX')->sole();

    expect(SectorAllocation::query()->count())->toBe(3)
        ->and(SectorAllocation::query()->where('asset_id', $chevron->id)->sole())
        ->sector->toBe(Sector::Energy)
        ->weight->toBe('1.000000');
});

it('only prices the instruments actually held', function () {
    $this->seed(SampleBackupSeeder::class);

    $nvidia = Instrument::query()->where('ticker', 'NVDA')->sole();
    $amundi = Instrument::query()->where('ticker', 'PUST.PA')->sole();

    expect(Price::query()->count())->toBe(3)
        ->and(Price::query()->where('asset_id', $nvidia->id)->exists())->toBeFalse()
        ->and(Price::query()->where('asset_id', $amundi->id)->count())->toBe(2);
});

it('stores prices with the canonical date format, one row per day', function () {
    $this->seed(SampleBackupSeeder::class);
    $this->seed(SampleBackupSeeder::class);

    $dates = DB::table('asset_prices')->select('asset_id', 'date')->get();
    $days = $dates->map(fn (object $row): string => $row->asset_id.'@'.substr((string) $row->date, 0, 10));

    expect($dates)->not->toBeEmpty()
        ->and($dates->every(fn (object $row): bool => strlen((string) $row->date) === 19))->toBeTrue()
        ->and($days->unique()->count())->toBe($dates->count());
});

it('replays the transactions and lets the observer project the holdings', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();
    $pea = Wallet::query()->where('user_id', $admin->id)->where('name', 'PEA')->sole();
    $amundi = Instrument::query()->where('ticker', 'PUST.PA')->sole();

    $holding = Holding::query()->where('wallet_id', $pea->id)->where('asset_id', $amundi->id)->sole();

    expect(Transaction::query()->count())->toBe(4)
        ->and(Holding::query()->count())->toBe(3)
        ->and((float) $holding->quantity)->toBe(5.0)
        // (2 × 100 + 3 × 110) / 5, hors frais.
        ->and((float) $holding->avg_cost)->toBe(106.0);
});

it('keeps the details of the transactions', function () {
    $this->seed(SampleBackupSeeder::class);

    $transaction = Transaction::query()->where('broker', 'IBKR')->sole();

    expect($transaction->notes)->toBe("Ligne d'exemple")
        ->and((float) $transaction->fees)->toBe(3.0)
        ->and($transaction->date->toDateString())->toBe('2026-01-06');
});

it('restores the tables that have no Eloquent model', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();
    $cto = Wallet::query()->where('user_id', $admin->id)->where('name', 'CTO')->sole();

    expect(DB::table('wallet_fees')->where('wallet_id', $cto->id)->value('name'))->toBe('Flat tax')
        ->and(DB::table('allocation_profiles')->count())->toBe(1)
        ->and(DB::table('allocation_profile_items')->count())->toBe(2)
        ->and(DB::table('invitations')->where('created_by', $admin->id)->count())->toBe(1)
        ->and(DB::table('feedback')->count())->toBe(2)
        ->and(DB::table('feedback')->orderBy('id')->value('body'))
        ->toBe("Ce serait pratique d'avoir un camembert.");
});

it('can be seeded twice without duplicating anything', function () {
    $usersBefore = User::query()->count();

    $this->seed(SampleBackupSeeder::class);
    $this->seed(SampleBackupSeeder::class);

    expect(User::query()->count())->toBe($usersBefore + 2)
        ->and(Wallet::query()->count())->toBe(3)
        ->and(Instrument::query()->count())->toBe(3)
        ->and(SectorAllocation::query()->count())->toBe(3)
        ->and(Price::query()->count())->toBe(3)
        ->and(Transaction::query()->count())->toBe(4)
        ->and(Holding::query()->count())->toBe(3)
        ->and(DB::table('wallet_fees')->count())->toBe(1)
        ->and(DB::table('allocation_profiles')->count())->toBe(1)
        ->and(DB::table('allocation_profile_items')->count())->toBe(2)
        ->and(DB::table('invitations')->count())->toBe(1)
        ->and(DB::table('feedback')->count())->toBe(2);
});

it('degrades gracefully when the dump is missing', function () {
    $usersBefore = User::query()->count();

    $this->seed(MissingBackupSeeder::class);

    expect(User::query()->count())->toBe($usersBefore)
        ->and(Instrument::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});
