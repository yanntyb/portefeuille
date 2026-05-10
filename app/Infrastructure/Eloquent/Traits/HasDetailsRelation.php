<?php

namespace App\Infrastructure\Eloquent\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasDetailsRelation
{
    // Returns relation to AssetInfo (created during CTI migration)
    public function details(): HasOne
    {
        return $this->hasOne('App\Domains\Asset\Models\AssetInfo', 'asset_id');
    }
}
