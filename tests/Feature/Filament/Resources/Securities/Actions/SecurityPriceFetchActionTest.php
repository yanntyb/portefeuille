<?php

use App\Filament\Pages\WalletPage;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\YahooFinanceService;

use function Pest\Livewire\livewire;

it('fetches missing prices on page load via refreshPrices', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    $peaWallet = Wallet::firstOrCreate(['user_id' => auth()->id(), 'name' => 'PEA']);

    $mock = Mockery::mock(YahooFinanceService::class);
    $mock->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->andReturnUsing(function ($securities) use ($security): int {
            SecurityPrice::factory()->create([
                'security_id' => $security->id,
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
