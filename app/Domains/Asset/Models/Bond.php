<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\BondFactory;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(BondFactory::class)]
class Bond extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<BondFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return BondAssetInfo::class;
    }

    public function getIsinAttribute(): ?string
    {
        return $this->details?->isin;
    }

    public function getTickerAttribute(): ?string
    {
        return $this->details?->ticker;
    }
}
