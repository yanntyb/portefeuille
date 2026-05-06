<?php

namespace App\Infrastructure\Contracts;

interface Storeable
{
    /** @return array<string, mixed> */
    public function toStore(): array;
}
