<?php

namespace App\Domains\Asset\Models;

class Crypto extends Asset
{
    /** @var list<string> */
    protected $fillable = ['name', 'type', 'isin', 'ticker'];
}
