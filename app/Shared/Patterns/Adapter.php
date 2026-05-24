<?php

namespace App\Shared\Patterns;

/**
 * Abstract adapter class for converting data from one format to another.
 * Subclasses must implement the adapt method to define conversion logic.
 */
abstract class Adapter
{
    /**
     * Adapt the source data to a new format.
     *
     * @return mixed The adapted data in the new format
     */
    abstract public function adapt(): mixed;
}
