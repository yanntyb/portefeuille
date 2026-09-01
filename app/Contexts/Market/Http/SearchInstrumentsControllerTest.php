<?php

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\InstrumentSearchResultData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\InstrumentProviderPort;

/** Un fournisseur qui rend ce qu'on lui dit, et retient ce qu'on lui a demandé. */
function fakeProvider(array $results = []): object
{
    $provider = new class($results) implements InstrumentProviderPort
    {
        public array $queries = [];

        public function __construct(private array $results) {}

        public function findBySymbol(string $symbol, InstrumentType $type): ?InstrumentData
        {
            return null;
        }

        public function supportsInstruments(InstrumentType $type): bool
        {
            return true;
        }

        public function searchInstruments(string $query): array
        {
            $this->queries[] = $query;

            return $this->results;
        }
    };

    app()->instance(InstrumentProviderPort::class, $provider);

    return $provider;
}

it('rend une liste vide sans appeler le fournisseur quand la requête est vide', function () {
    $provider = fakeProvider([new InstrumentSearchResultData(symbol: 'AAPL', name: 'Apple')]);

    $this->getJson('/instruments/recherche?q=')->assertOk()->assertExactJson([]);

    expect($provider->queries)->toBe([]);
});

it('marque d\'un identifiant les instruments déjà en base', function () {
    $instrument = Instrument::factory()->create(['name' => 'Apple Inc.', 'ticker' => 'AAPL']);
    fakeProvider();

    $this->getJson('/instruments/recherche?q=appl')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.symbol', 'AAPL')
        ->assertJsonPath('0.existingId', $instrument->id)
        ->assertJsonPath('0.typeLabel', 'Action');
});

it('cherche aussi par ticker', function () {
    Instrument::factory()->create(['name' => 'Amundi MSCI World', 'ticker' => 'CW8.PA']);
    fakeProvider();

    $this->getJson('/instruments/recherche?q=cw8')->assertOk()->assertJsonCount(1);
});

it('complète la base par le fournisseur, sans doubler un symbole connu', function () {
    Instrument::factory()->create(['name' => 'Apple Inc.', 'ticker' => 'AAPL']);

    fakeProvider([
        new InstrumentSearchResultData(symbol: 'AAPL', name: 'Apple Inc.', exchange: 'NasdaqGS', type: InstrumentType::Stock),
        new InstrumentSearchResultData(symbol: 'APLE', name: 'Apple Hospitality REIT', exchange: 'NYSE', type: InstrumentType::Stock),
    ]);

    $response = $this->getJson('/instruments/recherche?q=apple')->assertOk()->assertJsonCount(2);

    /** La ligne de la base d'abord, marquée ; le résultat neuf ensuite, sans identifiant. */
    $response->assertJsonPath('0.existingId', Instrument::query()->first()->id)
        ->assertJsonPath('1.symbol', 'APLE')
        ->assertJsonPath('1.existingId', null)
        ->assertJsonPath('1.exchange', 'NYSE');
});

it('rend un type nul quand le fournisseur n\'a pas su le traduire', function () {
    fakeProvider([new InstrumentSearchResultData(symbol: '^FCHI', name: 'CAC 40', exchange: 'Paris', type: null)]);

    $this->getJson('/instruments/recherche?q=cac')
        ->assertOk()
        ->assertJsonPath('0.type', null)
        ->assertJsonPath('0.typeLabel', null);
});

it('déduplique le fournisseur même quand l\'instrument connu est hors du plafond des dix lignes affichées', function () {
    foreach (range(1, 10) as $index) {
        Instrument::factory()->create([
            'name' => sprintf('Corp %02d', $index),
            'ticker' => sprintf('CORP%02d', $index),
        ]);
    }

    /** Onzième par ordre alphabétique : hors des dix lignes que `LOCAL_LIMIT` affiche. */
    Instrument::factory()->create(['name' => 'Zebra Corp', 'ticker' => 'ZCORP']);

    fakeProvider([
        new InstrumentSearchResultData(symbol: 'ZCORP', name: 'Zebra Corp', exchange: 'NYSE', type: InstrumentType::Stock),
    ]);

    $response = $this->getJson('/instruments/recherche?q=corp')->assertOk()->assertJsonCount(10);

    $duplicate = collect($response->json())
        ->first(fn (array $row): bool => $row['symbol'] === 'ZCORP' && $row['existingId'] === null);

    expect($duplicate)->toBeNull();
});
