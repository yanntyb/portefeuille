<?php

namespace App\Domains\Asset\Factories\Assets;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Factories\AssetInfos\CryptoAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Crypto;

/**
 * @extends AssetFactory<Crypto>
 */
class CryptoFactory extends AssetFactory
{
    /** @var class-string<Crypto> */
    protected $model = Crypto::class;

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

    protected function infos()
    {
        return CryptoAssetInfoFactory::new();
    }
}
