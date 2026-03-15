<?php

namespace App\Concerns;

use App\Extensions\Store;

trait HasTableStore
{
    public function dehydrateHasTableStore(): void
    {
        Store::add($this->tableStoreName(), $this->toTableStore(), persist: true);
    }

    /** @param array<string, mixed> $data */
    public function restoreFromTableStore(array $data): void
    {
        $this->fromTableStore($data);
    }
}
