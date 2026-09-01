<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Actions\CreateInstrument;
use App\Contexts\Market\Datas\InstrumentInputData;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Http\JsonResponse;

/**
 * JSON et non redirection, seule dérogation à la règle des routes d'écriture.
 *
 * La raison est le formulaire de transaction : le panneau de recherche s'y ouvre par-dessus une
 * saisie en cours, qui ne doit ni être perdue ni rejouée, et le formulaire a besoin de l'`id`
 * fraîchement créé pour le sélectionner. Une redirection ne le lui donnerait qu'au prix d'un flash
 * ou d'un rechargement complet des options.
 *
 * Le prix payé est côté client : les erreurs reviennent en 422 et se remappent à la main.
 */
class StoreInstrumentController
{
    public function __construct(private CreateInstrument $create) {}

    public function __invoke(InstrumentRequest $request): JsonResponse
    {
        $instrument = ($this->create)(InstrumentInputData::fromValidated($request->validated()));

        return response()->json($this->payload($instrument), 201);
    }

    /** @return array<string, mixed> */
    private function payload(Instrument $instrument): array
    {
        return [
            'id' => $instrument->id,
            'name' => $instrument->name,
            'ticker' => $instrument->ticker,
            'isin' => $instrument->isin,
            'type' => $instrument->type->value,
            'typeLabel' => $instrument->type->getLabel(),
            'assetClass' => $instrument->asset_class->value,
            'assetClassLabel' => $instrument->asset_class->getLabel(),
            /** Le client s'en sert pour aller au catalogue d'une autre exposition que la sienne. */
            'assetClassSlug' => $instrument->asset_class->slug(),
        ];
    }
}
