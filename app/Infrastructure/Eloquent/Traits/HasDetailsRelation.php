<?php

namespace App\Infrastructure\Eloquent\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasDetailsRelation
{
    abstract protected function getDetailsModel(): string;

    public function details(): HasOne
    {
        return $this->hasOne($this->getDetailsModel(), 'asset_id');
    }
}
