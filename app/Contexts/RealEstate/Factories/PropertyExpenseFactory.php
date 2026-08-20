<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyExpense> */
class PropertyExpenseFactory extends Factory
{
    protected $model = PropertyExpense::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'label' => null,
        ];
    }
}
