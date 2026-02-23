<?php

use App\Filament\Resources\AllSecurities\Pages\ListAllSecurities;
use App\Models\Security;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render the all securities list page', function () {
    $security = Security::factory()->create();

    livewire(ListAllSecurities::class)
        ->assertOk()
        ->assertCanSeeTableRecords(collect([$security]));
});

it('can search securities by name', function () {
    $target = Security::factory()->create(['name' => 'Tesla Inc.']);
    $other = Security::factory()->create(['name' => 'Amazon']);

    livewire(ListAllSecurities::class)
        ->searchTable('Tesla')
        ->assertCanSeeTableRecords(collect([$target]))
        ->assertCanNotSeeTableRecords(collect([$other]));
});

it('can update a security from ISIN via table action', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'exchange' => 'NMS', 'type' => 'Equity'],
            ],
        ])),
    ]);

    $security = Security::factory()->create([
        'isin' => 'US5949181045',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        'MSFT|Microsoft Corporation' => 'MSFT — Microsoft Corporation (NMS)',
    ]);

    livewire(ListAllSecurities::class)
        ->callTableAction(
            'updateFromIsin',
            $security,
            data: [
                'search_options' => $options,
                'selected_result' => 'MSFT|Microsoft Corporation',
            ],
        )
        ->assertHasNoTableActionErrors();

    assertDatabaseHas(Security::class, [
        'id' => $security->id,
        'ticker' => 'MSFT',
        'name' => 'Microsoft Corporation',
    ]);
});

it('shows warning when no results found for ISIN', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $security = Security::factory()->create([
        'isin' => 'XX0000000000',
        'name' => null,
        'ticker' => null,
    ]);

    livewire(ListAllSecurities::class)
        ->callTableAction('updateFromIsin', $security)
        ->assertNotified('Aucun résultat trouvé');
});
