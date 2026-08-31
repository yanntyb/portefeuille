<?php

use App\Contexts\Identity\Enums\Role;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\SectorProviderPort;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MissingBackupSeeder;
use Tests\Fixtures\SampleBackupSeeder;

/**
 * Le seeder ne rejoue plus les prix ni les secteurs du dump : il appelle `market:sync-prices`,
 * `market:sync-sectors` et `market:sync-dividends`. Le fournisseur est donc doublé dans chaque
 * test, sinon la suite interrogerait Yahoo.
 */
beforeEach(function () {
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn(['PUST.PA' => [
            new PriceData(date: '2026-01-02', close: 100.5),
            new PriceData(date: '2026-01-05', close: 110.5),
        ]]);
    });

    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturn([
            new SectorAllocationData(sector: Sector::Technology, weight: 1.0),
        ]);
    });

    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andReturn(['CVX' => [
            new DividendData(exDate: '2026-02-10', amountPerShare: 1.63),
        ]]);
    });
});

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

it('inserts the default account first, since the application falls back on it', function () {
    $this->seed(SampleBackupSeeder::class);

    expect(User::query()->orderBy('id')->value('email'))->toBe('admin@example.test');
});

it('grafts the default account onto the first user already in the database', function () {
    $host = User::factory()->create(['email' => 'test@example.com']);

    $this->seed(SampleBackupSeeder::class);

    expect(User::query()->orderBy('id')->value('email'))->toBe('test@example.com')
        ->and(User::query()->where('email', 'admin@example.test')->exists())->toBeFalse()
        ->and(Wallet::query()->where('user_id', $host->id)->pluck('name')->sort()->values()->all())
        ->toBe(['CTO', 'PEA', 'Portefeuille Crypto']);
});

it('lets every seeded account sign in with the shared password', function () {
    $this->seed(SampleBackupSeeder::class);

    expect(auth()->attempt(['email' => 'admin@example.test', 'password' => 'password']))->toBeTrue();
});

it('rebuilds the wallets of each user', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();

    // Le portefeuille crypto ne vient pas du dump, il est ouvert par le seeder.
    expect(Wallet::query()->count())->toBe(4)
        ->and(Wallet::query()->where('user_id', $admin->id)->pluck('name')->sort()->values()->all())
        ->toBe(['CTO', 'PEA', 'Portefeuille Crypto']);
});

it('type les enveloppes du dump d\'après leur nom', function () {
    $this->seed(SampleBackupSeeder::class);

    expect(Wallet::query()->where('name', 'PEA')->pluck('account_type')->unique()->all())
        ->toBe([AccountType::Pea])
        ->and(Wallet::query()->where('name', 'CTO')->pluck('account_type')->unique()->all())
        ->toBe([AccountType::Cto])
        ->and(Wallet::query()->where('name', 'Portefeuille Crypto')->value('account_type'))
        ->toBe(AccountType::Cto);
});

it('infers the instrument type absent from the dump', function () {
    $this->seed(SampleBackupSeeder::class);

    $types = Instrument::query()->pluck('type', 'ticker');

    expect(Instrument::query()->count())->toBe(5)
        ->and($types['PUST.PA'])->toBe(InstrumentType::ETF)
        ->and($types['BTC-EUR'])->toBe(InstrumentType::Crypto)
        ->and($types['ETH-EUR'])->toBe(InstrumentType::Crypto)
        ->and($types['CVX'])->toBe(InstrumentType::Stock)
        ->and($types['NVDA'])->toBe(InstrumentType::Stock)
        ->and(Instrument::query()->where('ticker', 'CVX')->value('isin'))->toBe('US1667641005');
});

it('takes the sectors from the provider and ignores those of the dump', function () {
    $this->mock(SectorProviderPort::class, function ($mock) {
        $mock->shouldReceive('supportsSectors')->andReturn(true);
        $mock->shouldReceive('getSectorAllocations')->andReturnUsing(
            fn (string $symbol): array => $symbol === 'CVX'
                ? [new SectorAllocationData(sector: Sector::Utilities, weight: 1.0)]
                : [],
        );
    });

    $this->seed(SampleBackupSeeder::class);

    $chevron = Instrument::query()->where('ticker', 'CVX')->sole();

    // Le dump classe CVX dans l'énergie : c'est bien le fournisseur qui tranche.
    expect(SectorAllocation::query()->count())->toBe(1)
        ->and(SectorAllocation::query()->where('asset_id', $chevron->id)->sole())
        ->sector->toBe(Sector::Utilities)
        ->weight->toBe('1.000000');
});

it('takes the prices from the provider, from the oldest transaction onwards', function () {
    $requests = [];

    $this->mock(PriceFeedPort::class, function ($mock) use (&$requests) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function (array $received) use (&$requests): array {
            $requests = $received;

            return ['PUST.PA' => [new PriceData(date: '2026-01-02', close: 100.5)]];
        });
    });

    $this->seed(SampleBackupSeeder::class);

    $amundi = Instrument::query()->where('ticker', 'PUST.PA')->sole();
    $startDates = array_map(fn (PriceRequestData $request): string => $request->startDate, $requests);

    // La transaction la plus ancienne du dump est datée du 2026-01-02.
    expect($startDates)->not->toBeEmpty()
        ->and(array_unique($startDates))->toBe(['2026-01-02'])
        ->and(Price::query()->count())->toBe(1)
        ->and((float) Price::query()->where('asset_id', $amundi->id)->sole()->close)->toBe(100.5);
});

it('takes the dividends from the provider, from the oldest transaction onwards', function () {
    $requests = [];

    $this->mock(DividendFeedPort::class, function ($mock) use (&$requests) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andReturnUsing(function (array $received) use (&$requests): array {
            $requests = $received;

            return ['CVX' => [new DividendData(exDate: '2026-02-10', amountPerShare: 1.63)]];
        });
    });

    $this->seed(SampleBackupSeeder::class);

    $chevron = Instrument::query()->where('ticker', 'CVX')->sole();
    $startDates = array_map(fn (DividendRequestData $request): string => $request->startDate, $requests);

    // La transaction la plus ancienne du dump est datée du 2026-01-02.
    expect($startDates)->not->toBeEmpty()
        ->and(array_unique($startDates))->toBe(['2026-01-02'])
        ->and(Dividend::query()->count())->toBe(1)
        ->and(Dividend::query()->where('asset_id', $chevron->id)->sole())
        ->ex_date->toDateString()->toBe('2026-02-10')
        ->and((float) Dividend::query()->where('asset_id', $chevron->id)->sole()->amount_per_share)->toBe(1.63);
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

    expect(Transaction::query()->count())->toBe(10)
        ->and(Holding::query()->count())->toBe(5)
        ->and((float) $holding->quantity)->toBe(5.0)
        // (2 × 100 + 3 × 110 + 2 € de frais) / 5 : les frais d'achat font partie du prix de revient.
        ->and((float) $holding->avg_cost)->toBe(106.4);
});

it('replays the crypto orders of the default account, without the cancelled one', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();
    $crypto = Wallet::query()->where('user_id', $admin->id)->where('name', 'Portefeuille Crypto')->sole();
    $bitcoin = Instrument::query()->where('ticker', 'BTC-EUR')->sole();
    $ethereum = Instrument::query()->where('ticker', 'ETH-EUR')->sole();

    $orders = Transaction::query()->where('wallet_id', $crypto->id)->orderBy('date')->get();
    $bitcoinHolding = Holding::query()->where('wallet_id', $crypto->id)->where('asset_id', $bitcoin->id)->sole();
    $ethereumHolding = Holding::query()->where('wallet_id', $crypto->id)->where('asset_id', $ethereum->id)->sole();

    // L'ordre annulé du 18 mai n'est pas rejoué : 6 achats, pas 7.
    expect($orders)->toHaveCount(6)
        ->and($orders->pluck('type')->unique()->all())->toBe([TransactionType::Buy])
        ->and($orders->pluck('broker')->unique()->all())->toBe(['Kraken'])
        ->and($orders->map(fn (Transaction $order): string => $order->date->toDateString())->all())
        ->toBe(['2026-05-18', '2026-06-01', '2026-07-01', '2026-07-31', '2026-08-31', '2026-08-31'])
        ->and((float) $orders->first()->unit_price)->toBe(66020.6)
        ->and((float) $bitcoinHolding->quantity)->toBe(0.00418712)
        ->and((float) $ethereumHolding->quantity)->toBe(0.02343151)
        // `transactions.fees` n'a que deux décimales : les 0,3985 € du relevé Kraken sont arrondis.
        ->and((float) $orders->last()->fees)->toBe(0.4);
});

it('keeps the details of the transactions', function () {
    $this->seed(SampleBackupSeeder::class);

    $transaction = Transaction::query()->where('notes', "Ligne d'exemple")->sole();

    expect((float) $transaction->fees)->toBe(3.0)
        ->and($transaction->date->toDateString())->toBe('2026-01-06');
});

it('books every transaction of the securities wallets at IBKR', function () {
    $this->seed(SampleBackupSeeder::class);

    $securities = Wallet::query()->whereIn('name', ['PEA', 'CTO'])->pluck('id');

    expect(Transaction::query()->whereIn('wallet_id', $securities)->pluck('broker')->unique()->all())
        ->toBe(['IBKR']);
});

it('books every securities wallet at IBKR and the crypto one at Kraken', function () {
    $this->seed(SampleBackupSeeder::class);

    expect(Wallet::query()->where('name', 'PEA')->first()->broker)->toBe('IBKR')
        ->and(Wallet::query()->where('name', 'CTO')->sole()->broker)->toBe('IBKR')
        ->and(Wallet::query()->where('name', 'Portefeuille Crypto')->sole()->broker)->toBe('Kraken');
});

it('restores the tables that have no Eloquent model', function () {
    $this->seed(SampleBackupSeeder::class);

    $admin = User::query()->where('email', 'admin@example.test')->sole();
    $cto = Wallet::query()->where('user_id', $admin->id)->where('name', 'CTO')->sole();

    expect(DB::table('wallet_fees')->where('wallet_id', $cto->id)->value('name'))->toBe('Flat tax');
});

it('can be seeded twice without duplicating anything', function () {
    $usersBefore = User::query()->count();

    $this->seed(SampleBackupSeeder::class);
    $this->seed(SampleBackupSeeder::class);

    expect(User::query()->count())->toBe($usersBefore + 2)
        ->and(Wallet::query()->count())->toBe(4)
        ->and(Instrument::query()->count())->toBe(5)
        ->and(SectorAllocation::query()->count())->toBe(5)
        ->and(Price::query()->count())->toBe(2)
        ->and(Dividend::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(10)
        ->and(Holding::query()->count())->toBe(5)
        ->and(DB::table('wallet_fees')->count())->toBe(1);
});

it('degrades gracefully when the dump is missing', function () {
    $usersBefore = User::query()->count();

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->seed(MissingBackupSeeder::class);

    expect(User::query()->count())->toBe($usersBefore)
        ->and(Instrument::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});
