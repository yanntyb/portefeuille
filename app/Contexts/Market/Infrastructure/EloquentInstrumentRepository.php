<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Eloquent\Collection;

class EloquentInstrumentRepository implements InstrumentRepositoryContract
{
    public function findById(int $id): ?Instrument
    {
        return Instrument::query()->find($id);
    }

    public function findByType(InstrumentType $type): Collection
    {
        return Instrument::query()->where('type', $type)->get();
    }

    public function findAll(): Collection
    {
        return Instrument::query()->get();
    }

    public function save(Instrument $asset): void
    {
        $asset->save();
    }
}
