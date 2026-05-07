<?php

use App\Domains\Analytics\Filament\Widgets\Dashboard\DashboardCorrelationMatrixWidget;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('returns null with fewer than two securities', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security->id,
    ]);

    SecurityPrice::factory()->count(25)->create([
        'security_id' => $security->id,
    ]);

    $data = livewire(DashboardCorrelationMatrixWidget::class)
        ->instance()
        ->getCorrelationData();

    expect($data)->toBeNull();
});

it('returns null with insufficient price history', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security2->id,
    ]);

    SecurityPrice::factory()->count(15)->create([
        'security_id' => $security1->id,
    ]);

    SecurityPrice::factory()->count(15)->create([
        'security_id' => $security2->id,
    ]);

    $data = livewire(DashboardCorrelationMatrixWidget::class)
        ->instance()
        ->getCorrelationData();

    expect($data)->toBeNull();
});

it('returns CorrelationResult with two securities having 25+ prices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security2->id,
    ]);

    for ($i = 0; $i < 25; $i++) {
        SecurityPrice::factory()->create([
            'security_id' => $security1->id,
            'date' => now()->subDays(25 - $i),
        ]);

        SecurityPrice::factory()->create([
            'security_id' => $security2->id,
            'date' => now()->subDays(25 - $i),
        ]);
    }

    $data = livewire(DashboardCorrelationMatrixWidget::class)
        ->instance()
        ->getCorrelationData();

    expect($data)->not->toBeNull();
});

it('filters to shownSecurityIds', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();
    $security3 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security2->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security3->id,
    ]);

    for ($i = 0; $i < 25; $i++) {
        SecurityPrice::factory()->create([
            'security_id' => $security1->id,
            'date' => now()->subDays(25 - $i),
        ]);

        SecurityPrice::factory()->create([
            'security_id' => $security2->id,
            'date' => now()->subDays(25 - $i),
        ]);

        SecurityPrice::factory()->create([
            'security_id' => $security3->id,
            'date' => now()->subDays(25 - $i),
        ]);
    }

    $widget = livewire(DashboardCorrelationMatrixWidget::class)->instance();
    $widget->shownSecurityIds = [$security1->id, $security2->id];

    $data = $widget->getCorrelationData();

    expect($data)->not->toBeNull();
});

it('returns null when shownSecurityIds reduces below two', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->pea()->create(['user_id' => $user->id]);
    $security1 = Security::factory()->create();
    $security2 = Security::factory()->create();

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security1->id,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'security_id' => $security2->id,
    ]);

    for ($i = 0; $i < 25; $i++) {
        SecurityPrice::factory()->create([
            'security_id' => $security1->id,
            'date' => now()->subDays(25 - $i),
        ]);

        SecurityPrice::factory()->create([
            'security_id' => $security2->id,
            'date' => now()->subDays(25 - $i),
        ]);
    }

    $widget = livewire(DashboardCorrelationMatrixWidget::class)->instance();
    $widget->shownSecurityIds = [$security1->id];

    $data = $widget->getCorrelationData();

    expect($data)->toBeNull();
});
