<?php

use App\Filament\Resources\CtoSecurities\Pages\EditCtoSecurity;
use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('does not have a create action in the CTO list page', function () {
    $security = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $security->id]);

    livewire(ListCtoSecurities::class)
        ->assertTableActionDoesNotExist('create');
});

it('can render the CTO edit page', function () {
    $security = Security::factory()->create();

    livewire(EditCtoSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSchemaStateSet([
            'isin' => $security->isin,
            'name' => $security->name,
        ]);
});

it('can update a security from CTO', function () {
    $security = Security::factory()->create();

    livewire(EditCtoSecurity::class, ['record' => $security->id])
        ->fillForm([
            'isin' => 'US5949181045',
            'name' => 'Microsoft Corporation',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'isin' => 'US5949181045',
        'name' => 'Microsoft Corporation',
    ]);
});

it('can search CTO securities by name', function () {
    $target = Security::factory()->create(['name' => 'Tesla Inc.']);
    Transaction::factory()->cto()->create(['security_id' => $target->id]);

    $other = Security::factory()->create(['name' => 'Amazon']);
    Transaction::factory()->cto()->create(['security_id' => $other->id]);

    livewire(ListCtoSecurities::class)
        ->searchTable('Tesla')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});

it('displays aggregated columns for a CTO security', function () {
    $security = Security::factory()->create();

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 5,
        'unit_price' => 200,
        'fees' => 3.00,
    ]);

    Transaction::factory()->cto()->create([
        'security_id' => $security->id,
        'quantity' => 15,
        'unit_price' => 250,
        'fees' => 7.00,
    ]);

    livewire(ListCtoSecurities::class)
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertTableColumnStateSet('total_quantity', '20.0000', $security);
});
