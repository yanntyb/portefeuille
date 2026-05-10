<?php

namespace App\Infrastructure\Eloquent\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasDetailsRelation
{
    abstract protected function getDetailsModel(): string;

    abstract protected function getDetailsForeignKey(): string;

    public function details(): HasOne
    {
        return $this->hasOne(
            $this->getDetailsModel(),
            $this->getDetailsForeignKey(),
            $this->getKeyName()
        );
    }
}
