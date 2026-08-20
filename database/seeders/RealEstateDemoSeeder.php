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

/** Un bien de démonstration : T2 loué, crédit en cours, un impayé, charges annuelles. */
class RealEstateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        $property = Property::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => 'T2 Croix-Rousse'],
            [
                'address' => '12 rue des Tables Claudiennes, 69001 Lyon',
                'acquisition_date' => '2024-03-15',
                'acquisition_price' => 165000,
                'acquisition_fees' => 13500,
            ],
        );

        // Idempotence: purge related records, then rebuild
        PropertyValuation::query()->where('property_id', $property->id)->delete();
        PropertyExpense::query()->where('property_id', $property->id)->delete();
        Loan::query()->where('property_id', $property->id)->delete();
        RentException::query()->whereIn('lease_id', Lease::query()->where('property_id', $property->id)->pluck('id'))->delete();
        Lease::query()->where('property_id', $property->id)->delete();

        $lease = Lease::query()->create([
            'property_id' => $property->id,
            'start_date' => '2024-05-01',
            'monthly_rent' => 680,
            'end_date' => null,
        ]);

        RentException::query()->create([
            'lease_id' => $lease->id,
            'month' => '2025-11-01',
            'amount_override' => 0,
            'note' => 'Impayé, régularisé en décembre',
        ]);

        Loan::query()->create([
            'property_id' => $property->id,
            'start_date' => '2024-03-15',
            'principal' => 145000,
            'annual_rate' => 0.0385,
            'term_months' => 240,
            'monthly_insurance' => 32,
        ]);

        foreach (['2024-10-12' => 780, '2025-10-10' => 810] as $date => $amount) {
            PropertyExpense::query()->create([
                'property_id' => $property->id,
                'date' => $date,
                'category' => ExpenseCategory::PropertyTax->value,
                'amount' => $amount,
                'label' => 'Taxe foncière',
            ]);
        }

        PropertyExpense::query()->create([
            'property_id' => $property->id,
            'date' => '2026-01-15',
            'category' => ExpenseCategory::CoOwnership->value,
            'amount' => 420,
            'label' => 'Charges de copropriété T1',
        ]);

        foreach (['2024-03-15' => 165000, '2026-02-01' => 178000] as $date => $value) {
            PropertyValuation::query()->create([
                'property_id' => $property->id,
                'date' => $date,
                'value' => $value,
            ]);
        }
    }
}
