<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\SecurityStatsOverview;
use App\Domains\Security\Filament\Widgets\ValuationChartWidget;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\Security\Services\YahooFinanceService;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('initializes shownSecurityIds with only securities that have today price', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();
    $securityNoPrice = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security1->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security2->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityNoPrice->id]);
    SecurityPrice::factory()->create(['security_id' => $security1->id, 'date' => today(), 'close' => 100]);
    SecurityPrice::factory()->create(['security_id' => $security2->id, 'date' => today(), 'close' => 200]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);

    expect($page->get('shownSecurityIds'))
        ->toContain($security1->id)
        ->toContain($security2->id)
        ->not->toContain($securityNoPrice->id);
});

it('removes a security id when toggling a visible security', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security1->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security2->id]);
    SecurityPrice::factory()->create(['security_id' => $security1->id, 'date' => today(), 'close' => 100]);
    SecurityPrice::factory()->create(['security_id' => $security2->id, 'date' => today(), 'close' => 200]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('toggleSecurity', $security1->id);

    expect($page->get('shownSecurityIds'))
        ->not->toContain($security1->id)
        ->toContain($security2->id);
});

it('adds back a security id when toggling a hidden security', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 100]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('toggleSecurity', $security->id)
        ->call('toggleSecurity', $security->id);

    expect($page->get('shownSecurityIds'))
        ->toContain($security->id);
});

it('dispatches prices-updated event after refreshPrices', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create(['ticker' => 'AAPL']);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);

    $mock = test()->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')->once()->andReturn(0);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('refreshPrices')
        ->assertDispatched('prices-updated');
});

it('skips fetch when all securities have current prices', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create(['ticker' => 'AAPL']);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 150]);

    $mock = test()->mock(YahooFinanceService::class);
    $mock->shouldNotReceive('fetchAndStorePricesBulk');

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('refreshPrices')
        ->assertDispatched('prices-updated');
});

it('fetches prices when at least one security lacks current price', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityWithPrice = Security::factory()->create(['ticker' => 'AAPL']);
    $securityWithoutPrice = Security::factory()->create(['ticker' => 'MSFT']);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithPrice->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithoutPrice->id]);
    SecurityPrice::factory()->create(['security_id' => $securityWithPrice->id, 'date' => today(), 'close' => 150]);

    $mock = test()->mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')->once()->andReturn(1);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('refreshPrices')
        ->assertDispatched('prices-updated');
});

it('dispatches security-visibility-changed event on toggle', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $security->id]);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->call('toggleSecurity', $security->id)
        ->assertDispatched('security-visibility-changed');
});

it('works on CTO wallet as well', function () {
    $ctoWallet = Wallet::factory()->cto()->create();
    $security = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $ctoWallet->id, 'security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => today(), 'close' => 100]);

    $page = livewire(WalletPage::class, ['walletId' => $ctoWallet->id]);

    expect($page->get('shownSecurityIds'))
        ->toContain($security->id);

    $page->call('toggleSecurity', $security->id);

    expect($page->get('shownSecurityIds'))
        ->not->toContain($security->id);
});

it('filters stats widget to only shown securities', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security1->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security2->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security1->id,
        'date' => now(),
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security2->id,
        'date' => now(),
        'close' => 250,
    ]);

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
        'shownSecurityIds' => [$security1->id],
    ]);

    $stats = invade($widget->instance())->getStats();

    // Only security1: valuation = 10 * 120 = 1200
    expect($stats[0]->getValue())->toContain('1,200');
});

it('filters chart widget to only shown securities', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security1->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security2->id,
        'user_id' => $user->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security1->id,
        'date' => '2024-01-15',
        'close' => 120,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security2->id,
        'date' => '2024-01-15',
        'close' => 250,
    ]);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'walletId' => $peaWallet->id,
        'shownSecurityIds' => [$security1->id],
    ]);

    $data = invade($widget->instance())->getData();

    // Only security1: valuation = 10 * 120 = 1200
    expect($data['datasets'][0]['data'][0])->toBe(1200.0);
});

it('shows empty stats when all securities are hidden', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => now(),
        'close' => 120,
    ]);

    $widget = livewire(SecurityStatsOverview::class, [
        'tablePageClass' => WalletPage::class,
        'shownSecurityIds' => [],
    ]);

    $stats = invade($widget->instance())->getStats();

    expect($stats[0]->getValue())->toContain('0');
});

it('displays error icon for securities without today price', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityWithPrice = Security::factory()->create();
    $securityWithoutPrice = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithPrice->id]);
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithoutPrice->id]);
    SecurityPrice::factory()->create(['security_id' => $securityWithPrice->id, 'date' => today(), 'close' => 100]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);
    $page->loadTable();

    $page->assertCanRenderTableColumn('name');

    $nameColumn = $page->instance()->getTable()->getColumn('name');

    $nameColumn->record($securityWithoutPrice);
    expect($nameColumn->getIcon($securityWithoutPrice->name))->toBe('heroicon-o-exclamation-triangle');
    expect($nameColumn->getTooltip())->toContain('prix');

    $nameColumn->record($securityWithPrice);
    expect($nameColumn->getIcon($securityWithPrice->name))->toBeNull();
    expect($nameColumn->getTooltip())->toBeNull();
});

it('keeps error icon after toggling a priceless security to visible', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityWithoutPrice = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithoutPrice->id]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);

    expect($page->get('pricelessSecurityIds'))->toContain($securityWithoutPrice->id);

    $page->call('toggleSecurity', $securityWithoutPrice->id);

    expect($page->get('shownSecurityIds'))->toContain($securityWithoutPrice->id);
    expect($page->get('pricelessSecurityIds'))->toContain($securityWithoutPrice->id);

    $nameColumn = $page->instance()->getTable()->getColumn('name');
    $nameColumn->record($securityWithoutPrice);
    expect($nameColumn->getIcon($securityWithoutPrice->name))->toBe('heroicon-o-exclamation-triangle');
});

it('does not show error icon after toggling a priced security to hidden', function () {
    $peaWallet = Wallet::factory()->pea()->create();
    $securityWithPrice = Security::factory()->create();
    Transaction::factory()->create(['wallet_id' => $peaWallet->id, 'security_id' => $securityWithPrice->id]);
    SecurityPrice::factory()->create(['security_id' => $securityWithPrice->id, 'date' => today(), 'close' => 100]);

    $page = livewire(WalletPage::class, ['walletId' => $peaWallet->id]);

    $page->call('toggleSecurity', $securityWithPrice->id);

    expect($page->get('shownSecurityIds'))->not->toContain($securityWithPrice->id);
    expect($page->get('pricelessSecurityIds'))->not->toContain($securityWithPrice->id);

    $nameColumn = $page->instance()->getTable()->getColumn('name');
    $nameColumn->record($securityWithPrice);
    expect($nameColumn->getIcon($securityWithPrice->name))->toBeNull();
});

it('shows empty chart when all securities are hidden', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $peaWallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'wallet_id' => $peaWallet->id,
        'security_id' => $security->id,
        'user_id' => $user->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 0,
        'date' => '2024-01-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-01-15',
        'close' => 120,
    ]);

    $widget = livewire(ValuationChartWidget::class, [
        'tablePageClass' => WalletPage::class,
        'shownSecurityIds' => [],
    ]);

    $data = invade($widget->instance())->getData();

    expect($data['datasets'])->toBeEmpty()
        ->and($data['labels'])->toBeEmpty();
});
