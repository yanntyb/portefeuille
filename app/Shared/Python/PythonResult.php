<?php

namespace App\Shared\Python;

readonly class PythonResult
{
    /**
     * @param  array<int|string, mixed>|null  $data
     */
    public function __construct(
        public string $status,
        public ?array $data = null,
        public ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->status === 'ok';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            status: $payload['status'] ?? 'error',
            data: is_array($payload['data'] ?? null) ? $payload['data'] : null,
            error: $payload['error'] ?? null,
        );
    }
}
