<?php

use App\Domains\Portfolio\Filament\Pages\WalletPage;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\Security\Services\YahooFinanceService;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

it('fetches missing prices on page load via refreshPrices', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $security = Security::factory()->create(['ticker' => 'AAPL']);
    Transaction::factory()->pea()->create(['asset_id' => $security->id, 'user_id' => $user->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $mock = Mockery::mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->andReturnUsing(function ($securities) use ($security): int {
            
SecurityPrice::factory()->create([
                'asset_id' => $security->id,
                'date' => now(),
                'close' => 100,
            ]);

            return 1;
        });

    app()->instance(YahooFinanceService::class, $mock);

    livewire(WalletPage::class, ['walletId' => $peaWallet->id])
        ->assertOk()
        ->call('refreshPrices')
        ->assertDispatched('prices-updated');
});
