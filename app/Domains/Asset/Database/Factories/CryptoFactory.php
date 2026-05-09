<?php

namespace App\Domains\Asset\Database\Factories;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Crypto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crypto>
 */
class CryptoFactory extends Factory
{
    protected $model = Crypto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $cryptos = ['Bitcoin', 'Ethereum', 'Cardano', 'Solana', 'Ripple'];
        $name = fake()->randomElement($cryptos);

        return [
            'isin' => fake()->numerify('###############'),
            'name' => $name,
            'ticker' => fake()->lexify('????'),
            'type' => AssetType::Crypto->value,
        ];
    }
}
