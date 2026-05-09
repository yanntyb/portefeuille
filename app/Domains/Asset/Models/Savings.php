<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\SavingsFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(SavingsFactory::class)]
class Savings extends Asset
{
    /** @use HasFactory<SavingsFactory> */
    use HasFactory;
}
