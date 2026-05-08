<?php

use App\Domains\Portfolio\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('displays the update from isin action on edit page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['asset_id' => $security->id]);

    livewire(EditWalletSecurity::class, ['record' => $security->getRouteKey()])
        ->assertActionVisible('updateFromIsin');
});
