<?php

namespace App\Shared\Abstractions;

abstract class Repository
{
    abstract public function find(mixed $id): mixed;

    abstract public function all(): array;
}
