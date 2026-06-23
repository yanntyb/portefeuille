<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Eloquent\Collection;

interface InstrumentRepositoryContract
{
    public function findById(int $id): ?Instrument;

    /** @return Collection<int, Instrument> */
    public function findByType(InstrumentType $type): Collection;

    /** @return Collection<int, Instrument> */
    public function findAll(): Collection;

    public function save(Instrument $asset): void;
}
