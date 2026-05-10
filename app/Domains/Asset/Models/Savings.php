<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\SavingsFactory;
use App\Infrastructure\Eloquent\Traits\HasDetailsRelation;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(SavingsFactory::class)]
class Savings extends Asset
{
    use HasDetailsRelation;

    /** @use HasFactory<SavingsFactory> */
    use HasFactory;

    protected function getDetailsModel(): string
    {
        return SavingsAssetInfo::class;
    }
}
