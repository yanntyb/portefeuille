<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Models\Security;
use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;

use function Pest\Livewire\livewire;

it('displays the update from isin action on edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(EditWalletSecurity::class, ['record' => $security->getRouteKey()])
        ->assertActionVisible('updateFromIsin');
});
