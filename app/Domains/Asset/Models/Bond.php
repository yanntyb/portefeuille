<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\BondFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(BondFactory::class)]
class Bond extends Asset
{
    /** @use HasFactory<BondFactory> */
    use HasFactory;
}
