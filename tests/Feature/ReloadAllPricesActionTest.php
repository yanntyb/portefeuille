<?php

use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

it('displays the update from isin action on edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(EditPeaSecurity::class, ['record' => $security->getRouteKey()])
        ->assertActionVisible('updateFromIsin');
});
