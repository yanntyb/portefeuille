<?php

namespace App\Domains\Asset\Models;

class Bond extends Asset
{
    /** @var list<string> */
    protected $fillable = ['name', 'type', 'isin', 'ticker'];
}
