<?php

use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Jobs\UpdateSecuritiesJob;
use App\Models\Security;
use App\Models\Transaction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

it('dispatches update job via header action', function () {
    Queue::fake();

    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->callAction(TestAction::make('fetchAllPrices')->table())
        ->assertNotified();

    Queue::assertPushed(UpdateSecuritiesJob::class, function (UpdateSecuritiesJob $job) use ($security) {
        return $job->securityIds === [$security->id];
    });
});
