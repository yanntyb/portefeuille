<?php

namespace App\Domains\Asset\Models;

class Savings extends Asset
{
    /** @var list<string> */
    protected $fillable = ['name', 'type', 'isin', 'ticker'];
}
