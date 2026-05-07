<?php

namespace App\Infrastructure\Services;

class UserId
{
    private ?int $overrideId = null;

    public function get(): int
    {
        if ($this->overrideId !== null) {
            return $this->overrideId;
        }

        $id = auth()->id();

        if ($id === null) {
            throw new \RuntimeException('No authenticated user and no UserId override set');
        }

        return $id;
    }

    public function setOverride(?int $id): void
    {
        $this->overrideId = $id;
    }
}
