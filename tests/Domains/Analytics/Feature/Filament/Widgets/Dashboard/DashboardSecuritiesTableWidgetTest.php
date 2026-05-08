<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\DashboardSecuritiesTableWidget;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('initializes shownSecurityIds with all user securities on mount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security2->id,
    ]);

    $widget = livewire(DashboardSecuritiesTableWidget::class)->instance();

    expect($widget->shownSecurityIds)->toHaveCount(2);
});

it('flags priceless securities in pricelessSecurityIds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $securityWithPrice = Security::factory()->create();
    $securityWithoutPrice = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $securityWithPrice->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $securityWithoutPrice->id,
    ]);

    
SecurityPrice::factory()->create([
        'asset_id' => $securityWithPrice->id,
        'date' => now()->toDateString(),
    ]);

    $widget = livewire(DashboardSecuritiesTableWidget::class)->instance();

    expect($widget->pricelessSecurityIds)->toContain($securityWithoutPrice->id);
});

it('toggleSecurity moves security to hiddenSecurityIds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    $widget = livewire(DashboardSecuritiesTableWidget::class)->instance();

    expect($widget->shownSecurityIds)->toContain($security->id)
        ->and($widget->hiddenSecurityIds)->not()->toContain($security->id);

    $widget->toggleSecurity($security->id);

    expect($widget->hiddenSecurityIds)->toContain($security->id)
        ->and($widget->shownSecurityIds)->not()->toContain($security->id);
});

it('double toggle restores security to shownSecurityIds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    $widget = livewire(DashboardSecuritiesTableWidget::class)->instance();

    $widget->toggleSecurity($security->id);
    expect($widget->hiddenSecurityIds)->toContain($security->id);

    $widget->toggleSecurity($security->id);
    expect($widget->hiddenSecurityIds)->not()->toContain($security->id)
        ->and($widget->shownSecurityIds)->toContain($security->id);
});

it('dispatches security-visibility-changed on toggle', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $security->id,
    ]);

    livewire(DashboardSecuritiesTableWidget::class)
        ->call('toggleSecurity', $security->id)
        ->assertDispatched('security-visibility-changed');
});

it('does not show securities from other users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $this->actingAs($user1);

    $wallet1 = Wallet::factory()->pea()->create(['user_id' => $user1->id]);
    $wallet2 = Wallet::factory()->pea()->create(['user_id' => $user2->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user1->id,
        'wallet_id' => $wallet1->id,
        'asset_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user2->id,
        'wallet_id' => $wallet2->id,
        'asset_id' => $security2->id,
    ]);

    $widget = livewire(DashboardSecuritiesTableWidget::class)->instance();

    expect($widget->shownSecurityIds)->toContain($security1->id)
        ->and($widget->shownSecurityIds)->not()->toContain($security2->id);
});
