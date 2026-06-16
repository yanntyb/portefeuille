<?php

namespace App\Shared\Python;

use RuntimeException;

class PythonProcessException extends RuntimeException
{
    public static function scriptNotFound(string $path): self
    {
        return new self("Python script not found: {$path}");
    }

    public static function processFailed(string $error): self
    {
        return new self("Python script failed: {$error}");
    }

    public static function invalidJson(string $message): self
    {
        return new self('Invalid JSON returned from Python script: '.$message);
    }
}
