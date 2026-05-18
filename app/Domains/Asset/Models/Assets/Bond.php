<?php

namespace App\Domains\Asset\Models\Assets;

use App\Domains\Asset\Factories\Assets\BondFactory;
use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;
use App\Infrastructure\Eloquent\Traits\HasInfos;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(BondFactory::class)]
class Bond extends Asset
{
    /** @use HasFactory<BondFactory> */
    use HasFactory;

    use HasInfos;

    protected function infosModel(): string
    {
        return BondAssetInfo::class;
    }
}
