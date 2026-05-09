<?php

namespace App\Domains\Asset\Models;

class RealEstate extends Asset
{
    /** @var list<string> */
    protected $fillable = ['name', 'type', 'isin', 'ticker'];
}
