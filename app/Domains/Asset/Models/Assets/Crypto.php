<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Database\Factories\CryptoFactory;
use App\Domains\Asset\Models\AssetInfos\CryptoAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(CryptoFactory::class)]
class Crypto extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<CryptoFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return CryptoAssetInfo::class;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->details?->ticker;
    }
}
