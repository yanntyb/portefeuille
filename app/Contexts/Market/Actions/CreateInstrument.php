<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Datas\InstrumentInputData;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use App\Contexts\Market\Models\Instrument;

/**
 * La première écriture d'instrument de l'application : jusqu'ici le catalogue ne venait que des
 * seeders.
 */
class CreateInstrument
{
    public function __invoke(InstrumentInputData $input): Instrument
    {
        /** Tableau littéral et non `$request->validated()` : `Instrument` est gardé par `['id']`. */
        $instrument = Instrument::query()->create([
            'name' => $input->name,
            'ticker' => $input->ticker,
            'isin' => $input->isin,
            'type' => $input->type,
            'asset_class' => $input->assetClass,
        ]);

        /** La fiche est atteignable tout de suite, vide, le temps que le job passe. */
        SyncInstrumentJob::dispatch($instrument->id);

        return $instrument;
    }
}
