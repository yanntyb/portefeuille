<?php

use App\Filament\Resources\CtoSecurities\Pages\ListCtoSecurities;
use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Filament\Resources\PeaSecurities\Pages\ListPeaSecurities;
use App\Models\Security;
use App\Models\Transaction;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render the PEA list page with only PEA securities', function () {
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $peaSecurity->id]);

    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $ctoSecurity->id]);

    livewire(ListPeaSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$peaSecurity]))
        ->assertCanNotSeeTableRecords(collect([$ctoSecurity]));
});

it('can render the CTO list page with only CTO securities', function () {
    $peaSecurity = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $peaSecurity->id]);

    $ctoSecurity = Security::factory()->create();
    Transaction::factory()->cto()->create(['security_id' => $ctoSecurity->id]);

    livewire(ListCtoSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$ctoSecurity]))
        ->assertCanNotSeeTableRecords(collect([$peaSecurity]));
});

it('displays aggregated columns for a PEA security', function () {
    $security = Security::factory()->create();

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 10,
        'unit_price' => 100,
        'fees' => 5.00,
    ]);

    Transaction::factory()->pea()->create([
        'security_id' => $security->id,
        'quantity' => 20,
        'unit_price' => 150,
        'fees' => 8.00,
    ]);

    livewire(ListPeaSecurities::class)
        ->assertCanSeeTableRecords(collect([$security]))
        ->assertTableColumnStateSet('total_quantity', '30.0000', $security);
});

it('can create a security from the table header action', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    livewire(ListPeaSecurities::class)
        ->callTableAction('create', data: [
            'isin' => 'FR0011871110',
            'name' => 'Amundi MSCI World',
        ])
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'isin' => 'FR0011871110',
        'name' => 'Amundi MSCI World',
    ]);
});

it('can render the PEA edit page', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->assertOk()
        ->assertSchemaStateSet([
            'isin' => $security->isin,
            'name' => $security->name,
        ]);
});

it('can update a security from PEA', function () {
    $security = Security::factory()->create();

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->fillForm([
            'isin' => 'US1667641005',
            'name' => 'Chevron Corporation',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'isin' => 'US1667641005',
        'name' => 'Chevron Corporation',
    ]);
});

it('can search PEA securities by name', function () {
    $target = Security::factory()->create(['name' => 'Amundi MSCI World']);
    Transaction::factory()->pea()->create(['security_id' => $target->id]);

    $other = Security::factory()->create(['name' => 'Chevron Corporation']);
    Transaction::factory()->pea()->create(['security_id' => $other->id]);

    livewire(ListPeaSecurities::class)
        ->searchTable('Amundi')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});
