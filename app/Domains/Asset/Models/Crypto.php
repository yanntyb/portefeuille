<?php

namespace App\Domains\Asset\Models;

use App\Domains\Asset\Database\Factories\CryptoFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[UseFactory(CryptoFactory::class)]
class Crypto extends Asset
{
    /** @use HasFactory<CryptoFactory> */
    use HasFactory;
}
