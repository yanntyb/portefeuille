<?php

use App\Filament\Resources\WalletSecurities\Pages\EditWalletSecurity;
use App\Models\Security;
use App\Services\YahooFinanceClient;

use function Pest\Livewire\livewire;

it('can search ticker from ISIN on security edit page', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([
                ['symbol' => '0P00000FMT.F', 'name' => 'CM-AM Dynamique International C', 'exchange' => 'Frankfurt', 'type' => 'Fund'],
            ]);
        $mock->shouldReceive('fetchPrices')->andReturn([]);
        $mock->shouldReceive('fetchSectors')->andReturn([]);
    });

    $security = Security::factory()->create([
        'isin' => 'FR0000447591',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        '0P00000FMT.F|CM-AM Dynamique International C' => '0P00000FMT.F — CM-AM Dynamique International C (Frankfurt)',
    ]);

    livewire(EditWalletSecurity::class, ['record' => $security->id])
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
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([
                ['symbol' => 'AEEM.PA', 'name' => 'Amundi MSCI Emerging Markets', 'exchange' => 'Paris', 'type' => 'ETF'],
            ]);
        $mock->shouldReceive('fetchPrices')->andReturn([]);
        $mock->shouldReceive('fetchSectors')->andReturn([]);
    });

    $security = Security::factory()->create([
        'isin' => 'FR0010359430',
        'name' => null,
        'ticker' => null,
    ]);

    $options = json_encode([
        'AEEM.PA|Amundi MSCI Emerging Markets' => 'AEEM.PA — Amundi MSCI Emerging Markets (Paris)',
    ]);

    livewire(EditWalletSecurity::class, ['record' => $security->id])
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

    livewire(EditWalletSecurity::class, ['record' => $security->id])
        ->fillForm(['isin' => ''])
        ->callAction('updateFromIsin')
        ->assertNotified('Veuillez renseigner un ISIN');
});

it('shows warning notification when no results found', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([]);
    });

    $security = Security::factory()->create([
        'isin' => 'XX0000000000',
        'name' => null,
        'ticker' => null,
    ]);

    livewire(EditWalletSecurity::class, ['record' => $security->id])
        ->callAction('updateFromIsin')
        ->assertNotified('Aucun résultat trouvé');
});
