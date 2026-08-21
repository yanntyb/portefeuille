<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Trois biens locatifs du Nord, achetés à crédit entre 2021 et 2024 autour de 80 000 €.
 *
 * Chacun expose un cas différent : Roubaix enchaîne deux baux séparés par une vacance,
 * Tourcoing encaisse de gros travaux dans les douze derniers mois, Douai a été rénové avant
 * sa mise en location et porte donc une année de charges sans le moindre loyer.
 */
class RealEstateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        $definitions = $this->definitions();

        $this->forgetPropertiesOutsideDemo($user->id, array_column($definitions, 'name'));

        foreach ($definitions as $definition) {
            $this->seedProperty($user->id, $definition);
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     address: string,
     *     acquisition_date: string,
     *     acquisition_price: int,
     *     acquisition_fees: int,
     *     loan: array{principal: int, annual_rate: float, term_months: int, monthly_insurance: int},
     *     leases: list<array{
     *         start_date: string,
     *         end_date: string|null,
     *         monthly_rent: int,
     *         exceptions: list<array{month: string, amount_override: int, note: string}>,
     *     }>,
     *     recurring: list<array{category: ExpenseCategory, amount: int, months: list<int>, label: string}>,
     *     expenses: list<array{date: string, category: ExpenseCategory, amount: int, label: string}>,
     *     valuations: array<string, int>,
     * }>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'T2 Roubaix Barbieux',
                'address' => '24 rue de Lannoy, 59100 Roubaix',
                'acquisition_date' => '2021-06-15',
                'acquisition_price' => 78000,
                'acquisition_fees' => 6400,
                'loan' => ['principal' => 70000, 'annual_rate' => 0.0115, 'term_months' => 240, 'monthly_insurance' => 18],
                'leases' => [
                    /* Le premier locataire part fin août 2023 : septembre et octobre sont vacants. */
                    [
                        'start_date' => '2021-09-01',
                        'end_date' => '2023-08-31',
                        'monthly_rent' => 520,
                        'exceptions' => [],
                    ],
                    /* Loyer révisé à la relocation : un nouveau bail, le précédent est clos. */
                    [
                        'start_date' => '2023-11-01',
                        'end_date' => null,
                        'monthly_rent' => 560,
                        'exceptions' => [
                            ['month' => '2026-03-01', 'amount_override' => 0, 'note' => 'Impayé, relance envoyée'],
                            ['month' => '2026-04-01', 'amount_override' => 280, 'note' => 'Régularisation partielle'],
                        ],
                    ],
                ],
                'recurring' => [
                    ['category' => ExpenseCategory::PropertyTax, 'amount' => 650, 'months' => [10], 'label' => 'Taxe foncière'],
                    ['category' => ExpenseCategory::Insurance, 'amount' => 95, 'months' => [1], 'label' => 'Assurance PNO'],
                    ['category' => ExpenseCategory::CoOwnership, 'amount' => 210, 'months' => [1, 4, 7, 10], 'label' => 'Charges de copropriété'],
                ],
                'expenses' => [
                    ['date' => '2022-05-18', 'category' => ExpenseCategory::Works, 'amount' => 1850, 'label' => 'Ravalement de façade (quote-part)'],
                    ['date' => '2026-05-06', 'category' => ExpenseCategory::Works, 'amount' => 620, 'label' => 'Remplacement de la VMC'],
                ],
                'valuations' => [
                    '2021-06-15' => 78000,
                    '2024-01-01' => 88000,
                    '2026-06-01' => 95000,
                ],
            ],
            [
                'name' => 'T3 Tourcoing Union',
                'address' => '8 rue de Lille, 59200 Tourcoing',
                'acquisition_date' => '2023-04-20',
                'acquisition_price' => 82000,
                'acquisition_fees' => 6900,
                'loan' => ['principal' => 75000, 'annual_rate' => 0.0315, 'term_months' => 240, 'monthly_insurance' => 22],
                'leases' => [
                    [
                        'start_date' => '2023-07-01',
                        'end_date' => null,
                        'monthly_rent' => 610,
                        'exceptions' => [
                            ['month' => '2025-12-01', 'amount_override' => 305, 'note' => 'Demi-loyer après dégât des eaux'],
                            ['month' => '2026-02-01', 'amount_override' => 0, 'note' => 'Impayé'],
                        ],
                    ],
                ],
                'recurring' => [
                    ['category' => ExpenseCategory::PropertyTax, 'amount' => 720, 'months' => [10], 'label' => 'Taxe foncière'],
                    ['category' => ExpenseCategory::Insurance, 'amount' => 105, 'months' => [1], 'label' => 'Assurance PNO'],
                    ['category' => ExpenseCategory::CoOwnership, 'amount' => 260, 'months' => [1, 4, 7, 10], 'label' => 'Charges de copropriété'],
                ],
                'expenses' => [
                    ['date' => '2026-05-12', 'category' => ExpenseCategory::Works, 'amount' => 2400, 'label' => 'Remplacement de la chaudière'],
                ],
                'valuations' => [
                    '2023-04-20' => 82000,
                    '2026-06-01' => 89000,
                ],
            ],
            [
                'name' => 'Maison de ville Douai',
                'address' => '5 rue Saint-Jacques, 59500 Douai',
                'acquisition_date' => '2024-09-10',
                'acquisition_price' => 79500,
                'acquisition_fees' => 6800,
                'loan' => ['principal' => 72000, 'annual_rate' => 0.0395, 'term_months' => 300, 'monthly_insurance' => 25],
                'leases' => [
                    /* Rénové d'abord, loué ensuite : 2024 ne porte que des charges. */
                    [
                        'start_date' => '2025-01-01',
                        'end_date' => null,
                        'monthly_rent' => 590,
                        'exceptions' => [
                            ['month' => '2025-11-01', 'amount_override' => 0, 'note' => 'Impayé, procédure en cours'],
                        ],
                    ],
                ],
                'recurring' => [
                    ['category' => ExpenseCategory::PropertyTax, 'amount' => 580, 'months' => [10], 'label' => 'Taxe foncière'],
                    ['category' => ExpenseCategory::Insurance, 'amount' => 90, 'months' => [1], 'label' => 'Assurance PNO'],
                    ['category' => ExpenseCategory::Management, 'amount' => 496, 'months' => [12], 'label' => 'Honoraires de gestion'],
                ],
                'expenses' => [
                    ['date' => '2024-10-22', 'category' => ExpenseCategory::Works, 'amount' => 5200, 'label' => 'Rafraîchissement avant mise en location'],
                ],
                'valuations' => [
                    '2024-09-10' => 79500,
                    '2026-06-01' => 84000,
                ],
            ],
        ];
    }

    /**
     * Le jeu de démo est la seule source de biens : ceux d'une version précédente du seeder
     * survivraient sinon à un `db:seed` lancé sur une base déjà peuplée.
     *
     * @param  list<string>  $names
     */
    private function forgetPropertiesOutsideDemo(int $userId, array $names): void
    {
        $stale = Property::query()
            ->where('user_id', $userId)
            ->whereNotIn('name', $names)
            ->get();

        foreach ($stale as $property) {
            $this->purgeRelated($property);
            $property->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProperty(int $userId, array $definition): void
    {
        $property = Property::query()->firstOrCreate(
            ['user_id' => $userId, 'name' => $definition['name']],
            [
                'address' => $definition['address'],
                'acquisition_date' => $definition['acquisition_date'],
                'acquisition_price' => $definition['acquisition_price'],
                'acquisition_fees' => $definition['acquisition_fees'],
            ],
        );

        $this->purgeRelated($property);

        Loan::query()->create([
            'property_id' => $property->id,
            'start_date' => $definition['acquisition_date'],
            'principal' => $definition['loan']['principal'],
            'annual_rate' => $definition['loan']['annual_rate'],
            'term_months' => $definition['loan']['term_months'],
            'monthly_insurance' => $definition['loan']['monthly_insurance'],
        ]);

        foreach ($definition['leases'] as $leaseDefinition) {
            $lease = Lease::query()->create([
                'property_id' => $property->id,
                'start_date' => $leaseDefinition['start_date'],
                'end_date' => $leaseDefinition['end_date'],
                'monthly_rent' => $leaseDefinition['monthly_rent'],
            ]);

            foreach ($leaseDefinition['exceptions'] as $exception) {
                RentException::query()->create([
                    'lease_id' => $lease->id,
                    'month' => $exception['month'],
                    'amount_override' => $exception['amount_override'],
                    'note' => $exception['note'],
                ]);
            }
        }

        foreach ($definition['expenses'] as $expense) {
            PropertyExpense::query()->create([
                'property_id' => $property->id,
                'date' => $expense['date'],
                'category' => $expense['category']->value,
                'amount' => $expense['amount'],
                'label' => $expense['label'],
            ]);
        }

        $this->seedRecurringExpenses($property, $definition);

        foreach ($definition['valuations'] as $date => $value) {
            PropertyValuation::query()->create([
                'property_id' => $property->id,
                'date' => $date,
                'value' => $value,
            ]);
        }
    }

    /**
     * Les charges qui reviennent chaque année sont écrites de l'acquisition à aujourd'hui,
     * jamais au-delà : le jeu de démo garde ainsi des charges dans la fenêtre des douze
     * derniers mois quelle que soit la date à laquelle on le rejoue.
     *
     * @param  array<string, mixed>  $definition
     */
    private function seedRecurringExpenses(Property $property, array $definition): void
    {
        $acquisition = Carbon::parse($definition['acquisition_date'])->startOfDay();
        $today = today();

        foreach ($definition['recurring'] as $recurring) {
            for ($year = $acquisition->year; $year <= $today->year; $year++) {
                foreach ($recurring['months'] as $month) {
                    $date = Carbon::create($year, $month, 15)->startOfDay();

                    if ($date->lt($acquisition) || $date->gt($today)) {
                        continue;
                    }

                    PropertyExpense::query()->create([
                        'property_id' => $property->id,
                        'date' => $date->toDateString(),
                        'category' => $recurring['category']->value,
                        'amount' => $recurring['amount'],
                        'label' => $recurring['label'],
                    ]);
                }
            }
        }
    }

    private function purgeRelated(Property $property): void
    {
        PropertyValuation::query()->where('property_id', $property->id)->delete();
        PropertyExpense::query()->where('property_id', $property->id)->delete();
        Loan::query()->where('property_id', $property->id)->delete();
        RentException::query()->whereIn('lease_id', Lease::query()->where('property_id', $property->id)->pluck('id'))->delete();
        Lease::query()->where('property_id', $property->id)->delete();
    }
}
