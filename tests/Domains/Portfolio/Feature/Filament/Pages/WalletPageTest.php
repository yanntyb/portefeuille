<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Filament\Widgets\AllocationChartWidget;
use App\Domains\Security\Filament\Widgets\CorrelationMatrixWidget;
use App\Domains\Security\Filament\Widgets\GainStatsOverview;
use App\Domains\Security\Filament\Widgets\PerformanceStatsOverview;
use App\Domains\Security\Filament\Widgets\SectorAllocationChartWidget;
use App\Domains\Security\Filament\Widgets\ValuationChartWidget;
use App\Domains\Security\Filament\Widgets\ValuationStatOverview;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns empty navigation when not authenticated', function () {
    expect(WalletPage::getNavigationItems())->toBeEmpty();
});

it('generates navigation items for user wallets', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'Livret']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    expect($items)->toHaveCount(3)
        ->and($items[0]->getLabel())->toBe('PEA')
        ->and($items[0]->getSort())->toBe(2)
        ->and($items[1]->getLabel())->toBe('CTO')
        ->and($items[1]->getSort())->toBe(3)
        ->and($items[2]->getLabel())->toBe('Livret')
        ->and($items[2]->getSort())->toBe(4);
});

it('marks navigation item active when walletId matches', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $this->actingAs($user);
    $this->get('/?walletId='.$wallet->id);

    $items = WalletPage::getNavigationItems();

    expect($items[0]->isActive())->toBeTrue();
});

it('uses correct icon for pea wallet', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    expect($items[0]->getIcon())->toEqual(\Filament\Support\Icons\Heroicon::OutlinedChartBar);
});

it('uses correct icon for cto wallet', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    expect($items[0]->getIcon())->toEqual(\Filament\Support\Icons\Heroicon::OutlinedBuildingLibrary);
});

it('uses correct icon for livret wallet', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'Livret']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    expect($items[0]->getIcon())->toEqual(\Filament\Support\Icons\Heroicon::OutlinedBanknotes);
});

it('uses default icon for unknown wallet type', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'Autre']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    expect($items[0]->getIcon())->toEqual(\Filament\Support\Icons\Heroicon::OutlinedWallet);
});

it('assigns correct sort order to wallets', function () {
    $user = User::factory()->create();
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'CTO']);
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'PEA']);
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'Livret']);
    Wallet::factory()->create(['user_id' => $user->id, 'name' => 'Autre']);

    $this->actingAs($user);

    $items = WalletPage::getNavigationItems();

    $sorts = [];
    foreach ($items as $item) {
        $sorts[$item->getLabel()] = $item->getSort();
    }

    expect($sorts['PEA'])->toBe(2)
        ->and($sorts['CTO'])->toBe(3)
        ->and($sorts['Livret'])->toBe(4)
        ->and($sorts['Autre'])->toBe(10);
});

it('renders wallet page with all widgets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    SecurityPrice::factory()->create(['security_id' => $security->id, 'date' => now()]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertOk()
        ->assertSeeLivewire(ValuationStatOverview::class)
        ->assertSeeLivewire(ValuationChartWidget::class)
        ->assertSeeLivewire(PerformanceStatsOverview::class)
        ->assertSeeLivewire(GainStatsOverview::class)
        ->assertSeeLivewire(AllocationChartWidget::class)
        ->assertSeeLivewire(SectorAllocationChartWidget::class)
        ->assertSeeLivewire(CorrelationMatrixWidget::class);
});
