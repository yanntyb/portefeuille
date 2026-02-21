<?php

use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Models\Security;
use App\Models\Transaction;
use App\Services\YahooFinanceService;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\mock;
use function Pest\Livewire\livewire;

it('can fetch all prices via header action', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    $service = mock(YahooFinanceService::class);
    $service->shouldReceive('fetchAndStorePrices')
        ->once()
        ->andReturn(5);

    livewire(ListPeaSecurities::class)
        ->callAction(TestAction::make('fetchAllPrices')->table())
        ->assertNotified();
});
