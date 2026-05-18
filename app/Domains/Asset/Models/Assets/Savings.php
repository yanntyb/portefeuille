<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\SavingsFactory;
use App\Domains\Asset\Models\AssetInfos\SavingsAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(SavingsFactory::class)]
class Savings extends Asset
{
    /** @use HasFactory<SavingsFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return SavingsAssetInfo::class;
    }
}
