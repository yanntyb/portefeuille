<?php

namespace App\Domains\Asset\Models;

use App\Domains\Security\Models\SecuritySector;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $isin
 * @property string $name
 * @property string $ticker
 * @property-read ?int $total_quantity
 * @property-read \Illuminate\Support\Carbon $created_at
 * @property-read \Illuminate\Support\Carbon $updated_at
 */
class Stock extends Asset
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'type',
        'isin',
        'ticker',
    ];

    public function sectors(): HasMany
    {
        return $this->hasMany(SecuritySector::class);
    }
}
