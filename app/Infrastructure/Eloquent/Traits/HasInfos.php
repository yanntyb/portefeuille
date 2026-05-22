<?php

namespace App\Infrastructure\Eloquent\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasInfos
{
    abstract protected function infosModel(): string;

    public function infos(): HasOne
    {
        return $this->hasOne(
            $this->infosModel(),
            'asset_id',
            $this->getKeyName()
        );
    }
}
