<?php

namespace App\Domains\Asset\Database\Factories\Assets;

use App\Domains\Asset\Database\Factories\AssetInfos\CryptoAssetInfoFactory;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Crypto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crypto>
 */
class CryptoFactory extends Factory
{
    protected $model = Crypto::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Crypto $crypto) {
            CryptoAssetInfoFactory::new()->create([
                'asset_id' => $crypto->id,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $cryptos = ['Bitcoin', 'Ethereum', 'Cardano', 'Solana', 'Ripple'];
        $name = fake()->randomElement($cryptos);

        return [
            'name' => $name,
            'type' => AssetType::Crypto->value,
        ];
    }
}
