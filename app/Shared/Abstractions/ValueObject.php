<?php

namespace App\Shared\Abstractions;

abstract class ValueObject
{
    abstract public function equals(self $other): bool;
}
