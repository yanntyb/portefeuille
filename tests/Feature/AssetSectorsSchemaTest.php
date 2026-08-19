<?php

use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La suite tourne sur une base fraîchement migrée : ces assertions verrouillent le schéma issu
 * des migrations de création, aucune table ni index ne devant reprendre le vocabulaire hérité
 * de l'époque `securities`.
 */
it('expose les secteurs sous le nom asset_sectors', function () {
    expect(Schema::hasTable('asset_sectors'))->toBeTrue()
        ->and(Schema::hasTable('security_sectors'))->toBeFalse();
});

it('écrit les pondérations sectorielles dans la table renommée', function () {
    $instrument = Instrument::factory()->create();

    SectorAllocation::query()->create([
        'asset_id' => $instrument->id,
        'sector' => Sector::Energy,
        'weight' => 1.0,
    ]);

    expect(DB::table('asset_sectors')->where('asset_id', $instrument->id)->count())->toBe(1);
});

it('ne déclare aucun index nommé security_', function () {
    $legacy = collect(indexNames('asset_sectors'))
        ->merge(indexNames('asset_prices'))
        ->filter(fn (string $name): bool => str_starts_with($name, 'security_'));

    expect($legacy)->toBeEmpty();
});

it('garde une contrainte unique sur le couple actif et secteur', function () {
    $instrument = Instrument::factory()->create();

    $payload = [
        'asset_id' => $instrument->id,
        'sector' => Sector::Energy->value,
        'weight' => 1.0,
    ];

    DB::table('asset_sectors')->insert($payload);

    expect(fn () => DB::table('asset_sectors')->insert($payload))
        ->toThrow(UniqueConstraintViolationException::class);
});

/**
 * @return list<string>
 */
function indexNames(string $table): array
{
    return array_map(
        fn (array $index): string => $index['name'],
        Schema::getIndexes($table),
    );
}
