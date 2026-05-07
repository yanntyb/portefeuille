<?php

use App\Domains\Security\Filament\Resources\AllSecurities\Pages\EditAllSecurity;
use App\Domains\Security\Filament\Resources\SecurityBase\RelationManagers\PricesRelationManager;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\User\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('can render the relation manager on the edit page', function () {
    $security = Security::factory()->create();

    livewire(EditAllSecurity::class, ['record' => $security->id])
        ->assertSeeLivewire(PricesRelationManager::class);
});

it('can list prices for a security', function () {
    $security = Security::factory()->create();
    $prices = SecurityPrice::factory()
        ->count(3)
        ->create(['security_id' => $security->id]);

    $otherPrice = SecurityPrice::factory()->create();

    livewire(PricesRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditAllSecurity::class,
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords($prices)
        ->assertCanNotSeeTableRecords(collect([$otherPrice]));
});

it('does not have create or edit actions', function () {
    $security = Security::factory()->create();

    livewire(PricesRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditAllSecurity::class,
    ])
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('edit');
});

it('can delete a price record', function () {
    $security = Security::factory()->create();
    $price = SecurityPrice::factory()->create(['security_id' => $security->id]);

    livewire(PricesRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditAllSecurity::class,
    ])
        ->callTableAction('delete', $price)
        ->assertHasNoActionErrors();

    expect(SecurityPrice::find($price->id))->toBeNull();
});

it('can bulk delete price records', function () {
    $security = Security::factory()->create();
    $prices = SecurityPrice::factory()
        ->count(3)
        ->create(['security_id' => $security->id]);

    livewire(PricesRelationManager::class, [
        'ownerRecord' => $security,
        'pageClass' => EditAllSecurity::class,
    ])
        ->callTableBulkAction('delete', $prices)
        ->assertHasNoActionErrors();

    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(0);
});
