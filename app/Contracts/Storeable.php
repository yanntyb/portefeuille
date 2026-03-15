<?php

namespace App\Contracts;

interface Storeable
{
    /** @return array<string, mixed> */
    public function toStore(): array;
}
