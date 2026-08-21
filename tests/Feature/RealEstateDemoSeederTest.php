<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetPropertyDetail;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use Database\Seeders\RealEstateDemoSeeder;

/** Le seeder se raccroche au premier utilisateur venu et ne fait rien s'il n'y en a pas. */
beforeEach(function () {
    $this->user = User::factory()->create();
});

/** @return list<RentMonthData> */
function demoRentHistory(string $name): array
{
    $property = Property::query()->where('name', $name)->sole();

    return app(GetPropertyDetail::class)($property->user_id, $property->id)->rentHistory;
}

it('crée trois biens du Nord achetés autour de 80 000 € à crédit', function () {
    $this->seed(RealEstateDemoSeeder::class);

    $properties = Property::query()->orderBy('acquisition_date')->get();

    expect($properties)->toHaveCount(3);

    foreach ($properties as $property) {
        expect($property->address)->toMatch('/ 59\d{3} /')
            ->and((float) $property->acquisition_price)->toBeGreaterThanOrEqual(75000.0)
            ->and((float) $property->acquisition_price)->toBeLessThanOrEqual(85000.0)
            ->and($property->loans()->count())->toBe(1)
            ->and($property->leases()->whereNull('end_date')->count())->toBe(1)
            ->and($property->valuations()->count())->toBeGreaterThanOrEqual(2);
    }

    expect($properties->pluck('acquisition_date')->map->year->all())->toBe([2021, 2023, 2024]);
});

it('sépare les deux baux de Roubaix par une vacance locative', function () {
    $this->seed(RealEstateDemoSeeder::class);

    $vacant = collect(demoRentHistory('T2 Roubaix Barbieux'))
        ->filter(fn (RentMonthData $month): bool => $month->expected === 0.0)
        ->pluck('month')
        ->all();

    expect($vacant)->toBe(['2023-10-01', '2023-09-01']);
});

it('laisse un impayé total et un impayé partiel dans l’historique des loyers', function () {
    $this->seed(RealEstateDemoSeeder::class);

    $unpaid = collect(demoRentHistory('T2 Roubaix Barbieux'))
        ->filter(fn (RentMonthData $month): bool => $month->expected > 0 && $month->effective < $month->expected);

    expect($unpaid->pluck('month')->all())->toBe(['2026-04-01', '2026-03-01'])
        ->and($unpaid->pluck('effective')->all())->toBe([280.0, 0.0]);
});

it('renouvelle les charges chaque année', function () {
    $this->seed(RealEstateDemoSeeder::class);

    $property = Property::query()->where('name', 'T3 Tourcoing Union')->sole();
    $years = $property->expenses()->where('label', 'Taxe foncière')->pluck('date')->map->year->all();

    expect($years)->toBe(range(2023, today()->month >= 10 ? today()->year : today()->year - 1));
});

it('n’écrit aucune charge postérieure à aujourd’hui', function () {
    $this->seed(RealEstateDemoSeeder::class);

    expect(PropertyExpense::query()->where('date', '>', today()->toDateString())->count())->toBe(0);
});

it('peut être rejoué sans dupliquer les biens ni leurs dépendances', function () {
    $this->seed(RealEstateDemoSeeder::class);

    $counts = fn (): array => [
        Property::query()->count(),
        Lease::query()->count(),
        RentException::query()->count(),
        Loan::query()->count(),
        PropertyExpense::query()->count(),
        PropertyValuation::query()->count(),
    ];

    $before = $counts();

    $this->seed(RealEstateDemoSeeder::class);

    expect($counts())->toBe($before);
});

it('supprime un bien hérité d’un jeu de démo précédent', function () {
    $legacy = Property::factory()->create(['user_id' => $this->user->id, 'name' => 'T2 Croix-Rousse']);
    Lease::factory()->create(['property_id' => $legacy->id]);

    $this->seed(RealEstateDemoSeeder::class);

    expect(Property::query()->where('name', 'T2 Croix-Rousse')->exists())->toBeFalse()
        ->and(Lease::query()->where('property_id', $legacy->id)->exists())->toBeFalse();
});
