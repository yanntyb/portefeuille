<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\CryptoFactory;
use App\Domains\Asset\Models\AssetInfos\CryptoAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(CryptoFactory::class)]
class Crypto extends Asset
{
    /** @use HasFactory<CryptoFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return CryptoAssetInfo::class;
    }

    public function getIsinAttribute(): ?string
    {
        return null;
    }
}
