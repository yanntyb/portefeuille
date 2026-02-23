<?php

use App\Filament\Resources\AllSecurities\Pages\EditAllSecurity;
use App\Models\Security;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->admin()->create());
});

it('can render the edit page', function () {
    $security = Security::factory()->create();

    livewire(EditAllSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSchemaStateSet([
            'isin' => $security->isin,
            'name' => $security->name,
            'ticker' => $security->ticker,
        ]);
});

it('can update a security', function () {
    $security = Security::factory()->create();

    livewire(EditAllSecurity::class, ['record' => $security->id])
        ->fillForm([
            'isin' => 'US1667641005',
            'name' => 'Chevron Corporation',
            'ticker' => 'CVX',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'isin' => 'US1667641005',
        'name' => 'Chevron Corporation',
        'ticker' => 'CVX',
    ]);
});
