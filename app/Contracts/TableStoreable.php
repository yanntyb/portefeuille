<?php

namespace App\Contracts;

interface TableStoreable
{
    public function tableStoreName(): string;

    /** @return array<string, mixed> */
    public function toTableStore(): array;

    /** @param array<string, mixed> $data */
    public function fromTableStore(array $data): void;
}
