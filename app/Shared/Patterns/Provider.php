<?php

namespace App\Shared\Patterns;

interface Provider
{
    /**
     * Register a service in the provider.
     *
     * @param  string  $name  The service name/key
     * @param  mixed  $service  The service instance or value
     */
    public function register(string $name, mixed $service): void;

    /**
     * Retrieve a registered service.
     *
     * @param  string  $name  The service name/key
     * @return mixed The registered service
     *
     * @throws RuntimeException If service is not found
     */
    public function get(string $name): mixed;

    /**
     * Check if a service is registered.
     *
     * @param  string  $name  The service name/key
     * @return bool True if service exists, false otherwise
     */
    public function has(string $name): bool;
}
