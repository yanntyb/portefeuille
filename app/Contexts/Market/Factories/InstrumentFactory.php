<?php

namespace App\Contexts\Market\Factories;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => InstrumentType::Stock,
            'isin' => fake()->optional()->regexify('[A-Z]{2}[A-Z0-9]{10}'),
            'ticker' => fake()->optional()->lexify('????'),
        ];
    }

    public function ofType(InstrumentType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function withPrices(callable $configure): static
    {
        return $this->afterCreating(function (Instrument $instrument) use ($configure): void {
            $factory = $configure(PriceFactory::new());
            $factory->create(['asset_id' => $instrument->id]);
        });
    }

    public function withSectors(callable $configure): static
    {
        return $this->afterCreating(function (Instrument $instrument) use ($configure): void {
            $factory = $configure(SectorAllocationFactory::new());
            $factory->create(['asset_id' => $instrument->id]);
        });
    }
}
