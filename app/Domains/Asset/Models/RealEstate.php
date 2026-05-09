<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\RealEstateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(RealEstateFactory::class)]
class RealEstate extends Asset
{
    /** @use HasFactory<RealEstateFactory> */
    use HasFactory;
}
