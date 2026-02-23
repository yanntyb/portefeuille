<?php

use App\Filament\Resources\PeaSecurities\Pages\EditPeaSecurity;
use App\Models\Security;
use Illuminate\Support\Facades\Process;

use function Pest\Livewire\livewire;

it('can search ticker from ISIN on security edit page', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => '0P00000FMT.F', 'name' => 'CM-AM Dynamique International C', 'exchange' => 'Frankfurt', 'type' => 'Fund'],
            ],
        ])),
    ]);

    $security = Security::factory()->create([
        'isin' => 'FR0000447591',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        '0P00000FMT.F|CM-AM Dynamique International C' => '0P00000FMT.F — CM-AM Dynamique International C (Frankfurt)',
    ]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->callAction(
            'updateFromIsin',
            data: [
                'search_options' => $options,
                'selected_result' => '0P00000FMT.F|CM-AM Dynamique International C',
            ],
        )
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet([
            'ticker' => '0P00000FMT.F',
            'name' => 'CM-AM Dynamique International C',
        ]);
});

it('can search ticker from ISIN FR0010359430 on security edit page', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'AEEM.PA', 'name' => 'Amundi MSCI Emerging Markets', 'exchange' => 'Paris', 'type' => 'ETF'],
            ],
        ])),
    ]);

    $security = Security::factory()->create([
        'isin' => 'FR0010359430',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        'AEEM.PA|Amundi MSCI Emerging Markets' => 'AEEM.PA — Amundi MSCI Emerging Markets (Paris)',
    ]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->callAction(
            'updateFromIsin',
            data: [
                'search_options' => $options,
                'selected_result' => 'AEEM.PA|Amundi MSCI Emerging Markets',
            ],
        )
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet([
            'ticker' => 'AEEM.PA',
            'name' => 'Amundi MSCI Emerging Markets',
        ]);
});

it('shows warning notification when ISIN is empty on security edit page', function () {
    $security = Security::factory()->create([
        'isin' => 'FR0000447591',
        'name' => null,
        'ticker' => null,
    ]);

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->fillForm(['isin' => ''])
        ->callAction('updateFromIsin')
        ->assertNotified('Veuillez renseigner un ISIN');
});

it('shows warning notification when no results found', function () {
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

    livewire(EditPeaSecurity::class, ['record' => $security->id])
        ->callAction('updateFromIsin')
        ->assertNotified('Aucun résultat trouvé');
});
